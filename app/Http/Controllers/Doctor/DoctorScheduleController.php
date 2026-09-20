<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\DoctorScheduleException;
use App\Models\DoctorWeeklySchedule;
use App\Models\DoctorWeeklyScheduleSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class DoctorScheduleController extends Controller
{
    /**
     * My Schedule — Weekly Availability + Schedule Exceptions.
     */
    public function index()
    {
        $doctor = Auth::user();

        // One row per day, in Monday..Sunday order, with slots loaded.
        $existing = DoctorWeeklySchedule::with('slots')
            ->where('doctor_id', $doctor->id)
            ->get()
            ->keyBy('day_of_week');

        $weeklySchedules = collect(DoctorWeeklySchedule::DAYS)->map(function ($day) use ($existing) {
            return $existing->get($day); // null if the doctor hasn't set this day yet
        });

        $exceptions = DoctorScheduleException::where('doctor_id', $doctor->id)
            ->orderBy('exception_date')
            ->get();

        return view('doctor.schedule', [
            'days'            => DoctorWeeklySchedule::DAYS,
            'weeklySchedules' => $weeklySchedules, // keyed 0..6 matching DAYS order
            'exceptions'      => $exceptions,
        ]);
    }

    /**
     * Create or update the weekly schedule for a single day (upsert by day).
     * The Edit Schedule modal lets the doctor pick/change the day, so this
     * always resolves the target row by the submitted day_of_week — not by
     * a route id — and creates it if it doesn't exist yet.
     */
    public function updateWeekly(Request $request)
    {
        $doctor = Auth::user();

        $validated = $request->validate([
            'day_of_week'          => ['required', Rule::in(DoctorWeeklySchedule::DAYS)],
            'status'                => ['required', Rule::in(['Available', 'Unavailable'])],
            'slots'                 => ['array'],
            'slots.*.start_time'    => ['required_with:slots', 'date_format:H:i'],
            'slots.*.end_time'      => ['required_with:slots', 'date_format:H:i'],
        ]);

        $slots = collect($validated['slots'] ?? []);

        if ($validated['status'] === 'Available') {
            if ($slots->isEmpty()) {
                throw ValidationException::withMessages([
                    'slots' => 'Add at least one time slot for an available day.',
                ]);
            }

            // Every slot's end time must be later than its start time.
            foreach ($slots as $slot) {
                $start = Carbon::createFromFormat('H:i', $slot['start_time']);
                $end   = Carbon::createFromFormat('H:i', $slot['end_time']);

                if ($end->lessThanOrEqualTo($start)) {
                    throw ValidationException::withMessages([
                        'slots' => 'End time must be later than start time for every slot.',
                    ]);
                }
            }

            $this->assertNoOverlappingSlots($slots);
        } else {
            // Unavailable days never carry time slots.
            $slots = collect();
        }

        DB::transaction(function () use ($doctor, $validated, $slots) {
            $weekly = DoctorWeeklySchedule::updateOrCreate(
                ['doctor_id' => $doctor->id, 'day_of_week' => $validated['day_of_week']],
                ['status' => $validated['status']]
            );

            // Replace slots wholesale — simplest way to honor add/edit/remove
            // of multiple time slots from a single modal submission.
            $weekly->slots()->delete();

            foreach ($slots as $slot) {
                DoctorWeeklyScheduleSlot::create([
                    'doctor_weekly_schedule_id' => $weekly->id,
                    'start_time'                => $slot['start_time'],
                    'end_time'                  => $slot['end_time'],
                ]);
            }
        });

        return redirect()->route('doctor.schedule.index')
            ->with('success', $validated['day_of_week'] . '\'s schedule updated successfully!');
    }

    /**
     * Reset a day back to Unavailable / no time slots ("delete" the schedule
     * for that day). Existing appointments already booked are left untouched
     * — this only affects future booking availability.
     */
    public function destroyWeekly(string $day)
    {
        $doctor = Auth::user();

        if (!in_array($day, DoctorWeeklySchedule::DAYS, true)) {
            abort(404);
        }

        DoctorWeeklySchedule::where('doctor_id', $doctor->id)
            ->where('day_of_week', $day)
            ->delete(); // slots cascade-delete

        return redirect()->route('doctor.schedule.index')
            ->with('success', $day . '\'s schedule was cleared.');
    }

    /**
     * Add a schedule exception (leave/holiday or custom hours) for a date.
     */
    public function storeException(Request $request)
    {
        $doctor = Auth::user();

        $validated = $this->validateException($request, $doctor->id);

        DoctorScheduleException::create([
            'doctor_id'      => $doctor->id,
            'exception_date' => $validated['exception_date'],
            'type'           => $validated['type'],
            'start_time'     => $validated['start_time'] ?? null,
            'end_time'       => $validated['end_time'] ?? null,
            'reason'         => $validated['reason'] ?? null,
        ]);

        return redirect()->route('doctor.schedule.index')
            ->with('success', 'Schedule exception added successfully!');
    }

    /**
     * Update an existing schedule exception.
     */
    public function updateException(Request $request, DoctorScheduleException $exception)
    {
        $this->authorizeOwnException($exception);

        $validated = $this->validateException($request, $exception->doctor_id, $exception->id);

        $exception->update([
            'exception_date' => $validated['exception_date'],
            'type'           => $validated['type'],
            'start_time'     => $validated['start_time'] ?? null,
            'end_time'       => $validated['end_time'] ?? null,
            'reason'         => $validated['reason'] ?? null,
        ]);

        return redirect()->route('doctor.schedule.index')
            ->with('success', 'Schedule exception updated successfully!');
    }

    /**
     * Delete a schedule exception. Existing appointments are left untouched.
     */
    public function destroyException(DoctorScheduleException $exception)
    {
        $this->authorizeOwnException($exception);

        $exception->delete();

        return redirect()->route('doctor.schedule.index')
            ->with('success', 'Schedule exception deleted.');
    }

    /**
     * A doctor may only ever manage their own schedule exceptions.
     */
    private function authorizeOwnException(DoctorScheduleException $exception): void
    {
        if ($exception->doctor_id !== Auth::id()) {
            abort(403, 'You may not manage another doctor\'s schedule.');
        }
    }

    private function validateException(Request $request, int $doctorId, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'exception_date' => [
                'required',
                'date',
                Rule::unique('doctor_schedule_exceptions', 'exception_date')
                    ->where(fn ($q) => $q->where('doctor_id', $doctorId))
                    ->ignore($ignoreId),
            ],
            'type'       => ['required', Rule::in(['Unavailable', 'Custom Hours'])],
            'start_time' => ['nullable', 'date_format:H:i', 'required_if:type,Custom Hours'],
            'end_time'   => ['nullable', 'date_format:H:i', 'required_if:type,Custom Hours', 'after:start_time'],
            'reason'     => ['nullable', 'string', 'max:500'],
        ], [
            'exception_date.unique' => 'A schedule exception already exists for this date.',
            'end_time.after'        => 'End time must be later than start time.',
        ]);

        if ($validated['type'] === 'Unavailable') {
            $validated['start_time'] = null;
            $validated['end_time']   = null;
        }

        return $validated;
    }

    /**
     * Ensure none of the submitted time slots for a single day overlap.
     */
    private function assertNoOverlappingSlots($slots): void
    {
        $sorted = $slots->sortBy('start_time')->values();

        for ($i = 0; $i < $sorted->count() - 1; $i++) {
            $currentEnd = Carbon::createFromFormat('H:i', $sorted[$i]['end_time']);
            $nextStart  = Carbon::createFromFormat('H:i', $sorted[$i + 1]['start_time']);

            if ($currentEnd->greaterThan($nextStart)) {
                throw ValidationException::withMessages([
                    'slots' => 'Time slots cannot overlap with each other.',
                ]);
            }
        }
    }
}
