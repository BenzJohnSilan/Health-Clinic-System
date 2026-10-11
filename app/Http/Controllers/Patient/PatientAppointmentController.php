<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\User;
use App\Models\UserLog;
use App\Models\DoctorWeeklySchedule;
use App\Models\DoctorScheduleException;
use App\Models\LaboratoryRequest;
use App\Services\DoctorAvailabilityService;
use Carbon\Carbon;

class PatientAppointmentController extends Controller
{
    /**
     * Show patient appointments
     */
    public function index(Request $request)
    {
        $patient = auth()->user();

        $approvedAppointments = Appointment::with('doctor')
            ->where('patient_id', $patient->id)
            ->where('status', 'Approved')
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->paginate(10);

        $allAppointments = Appointment::with('doctor')
            ->where('patient_id', $patient->id)
            ->whereIn('status', ['Approved', 'Pending', 'Rescheduled', 'Checked In', 'In Progress',])
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'asc')
            ->paginate(10);

        $doctors = User::where('role', 'Doctor')->get();

        $bookedSlots = Appointment::whereNotIn('status', ['Rejected', 'Cancelled'])
            ->whereNotNull('appointment_time')   // walk-ins have no reserved slot
            ->get(['doctor_id', 'appointment_date', 'appointment_time']);

        // ✅ DOCTOR AVAILABILITY DATA — used client-side to grey out doctors
        // and time slots that fall outside a doctor's configured weekly
        // schedule or that are hit by a schedule exception.
        //
        // IMPORTANT: a doctor with NO rows here has NOT configured a
        // schedule at all and must be treated as fully unavailable, both
        // here (client-side) and in DoctorAvailabilityService (server-side).
        // There is no "unrestricted" fallback — see store() below, which is
        // the authoritative check and cannot be bypassed by the frontend.
        $weeklyAvailability = DoctorWeeklySchedule::with('slots')
            ->get()
            ->groupBy('doctor_id')
            ->map(function ($schedules) {
                return $schedules->keyBy('day_of_week')->map(function ($schedule) {
                    return [
                        'status' => $schedule->status,
                        'slots'  => $schedule->slots->map(fn ($s) => [
                            'start' => substr($s->start_time, 0, 5),
                            'end'   => substr($s->end_time, 0, 5),
                        ])->values(),
                    ];
                });
            });

        $exceptionsByDoctor = DoctorScheduleException::whereDate('exception_date', '>=', Carbon::today())
            ->get()
            ->groupBy('doctor_id')
            ->map(function ($exceptions) {
                return $exceptions->keyBy(fn ($e) => Carbon::parse($e->exception_date)->format('Y-m-d'))
                    ->map(fn ($e) => [
                        'type'  => $e->type,
                        'start' => $e->start_time ? substr($e->start_time, 0, 5) : null,
                        'end'   => $e->end_time ? substr($e->end_time, 0, 5) : null,
                    ]);
            });

        // ✅ PROFILE COMPLETION STATUS — used to hide/disable booking in the UI.
        // NOTE: this is a UX convenience only; the real enforcement happens
        // server-side in store() below regardless of what the frontend shows.
        $profileComplete = $patient->computeProfileComplete();

        // ✅ FOLLOW-UP CONSULTATION (Laboratory Result follow-up).
        // Every Reviewed laboratory request of THIS patient where the
        // Doctor recommended a follow-up that hasn't been booked yet.
        // Used to (a) offer "Book Follow-up / Continue as Regular" when
        // the patient clicks the normal Book Appointment button — a new
        // appointment is NEVER assumed to be a follow-up — and (b) open
        // the booking form directly in follow-up mode from the
        // Laboratory Results page (?follow_up={id}).
        $pendingFollowUps = LaboratoryRequest::with(['doctor', 'tests'])
            ->where('patient_id', $patient->id)
            ->awaitingFollowUpBooking()
            ->orderByDesc('reviewed_at')
            ->get();

        // Follow-up mode is selected either from the link (?follow_up=)
        // or replayed after a failed submit (old input), and only ever
        // for one of this patient's own eligible requests.
        $followUpId = $request->old('follow_up_laboratory_request_id') ?? $request->query('follow_up');
        $followUpRequest = $followUpId ? $pendingFollowUps->firstWhere('id', (int) $followUpId) : null;

        // Shown as a plain page notice (not session 'error', which would
        // auto-open the booking modal).
        $followUpNotice = ($request->query('follow_up') && !$followUpRequest)
            ? 'This laboratory result has no follow-up consultation waiting to be booked (it may already be booked).'
            : null;

        return view('patient.appointments', [
            'pendingFollowUps'     => $pendingFollowUps,
            'followUpRequest'      => $followUpRequest,
            'followUpNotice'       => $followUpNotice,
            'doctors'              => $doctors,
            'upcomingAppointments' => $approvedAppointments,
            'appointments'         => $allAppointments,
            'bookedSlots'          => $bookedSlots,
            'profileComplete'      => $profileComplete,
            'weeklyAvailability'   => $weeklyAvailability,
            'exceptionsByDoctor'   => $exceptionsByDoctor,
        ]);
    }

    /**
     * Store new appointment
     */
    public function store(Request $request)
    {
        $patient = auth()->user();

        // ===================================================================
        // ✅ SERVER-SIDE APPOINTMENT BOOKING PROTECTION (cannot be bypassed
        // by disabling JS, hiding buttons, or hitting this route directly).
        //
        //   Email Verified  AND  Admin Approved  AND  Required Profile Complete
        //   ---------------------------------------------------------------
        //                     -> allow booking
        // ===================================================================
        if (!$patient->hasVerifiedEmail() || $patient->approval_status !== 'Approved') {
            return redirect()->route('patient.dashboard')
                ->with('error', 'Your account must be verified and approved before booking an appointment.');
        }

        if (!$patient->computeProfileComplete()) {
            return redirect()->route('patient.settings')
                ->with('error', 'Please complete your required patient information before booking an appointment.');
        }

        $request->validate([
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required',
            'doctor_id'        => 'required|exists:users,id',
            'reason'           => 'required|string|max:255',
            'follow_up_laboratory_request_id' => 'nullable|integer',
        ]);

        // ✅ FOLLOW-UP CONSULTATION — only when the patient explicitly
        // chose "Book Follow-up Consultation". The laboratory request is
        // re-checked here from the database (ownership, Reviewed,
        // follow-up recommended, not already booked) — never trusted
        // from the form. Without this field the appointment is Regular.
        $followUpLabRequest = null;

        if ($request->filled('follow_up_laboratory_request_id')) {
            $followUpLabRequest = LaboratoryRequest::where('patient_id', $patient->id)
                ->awaitingFollowUpBooking()
                ->find((int) $request->follow_up_laboratory_request_id);

            if (!$followUpLabRequest) {
                return redirect()->route('patient.appointments.index')
                    ->with('error', 'This laboratory result has no follow-up consultation waiting to be booked.');
            }

            // A follow-up is with the Doctor who requested/reviewed the
            // laboratory result (the only Doctor authorized on it).
            if ($followUpLabRequest->doctor_id && (int) $request->doctor_id !== (int) $followUpLabRequest->doctor_id) {
                return redirect()->back()->withInput()
                    ->with('error', 'A follow-up consultation must be booked with the doctor who reviewed your laboratory result.');
            }
        }

        $appointmentDateTime = Carbon::parse(
            $request->appointment_date . ' ' . $request->appointment_time
        );

        if ($appointmentDateTime->isPast()) {
            return redirect()->back()->withInput()
                ->with('error', 'You cannot book an appointment in the past.');
        }

        // ✅ DOCTOR AVAILABILITY CHECK — the doctor's weekly schedule and
        // schedule exceptions are re-checked here from the database,
        // regardless of what the frontend shows. This is the authoritative
        // check and cannot be bypassed by editing the form, disabling JS,
        // or submitting the request directly (e.g. via curl/Postman).
        //
        // A doctor with no schedule at all, a day with no schedule row, a
        // day marked Unavailable, an Available day with no time slots, and
        // an Unavailable/Custom Hours exception are ALL rejected here.
        [$isAvailable, $unavailableReason] = DoctorAvailabilityService::isAvailable(
            (int) $request->doctor_id,
            $request->appointment_date,
            $request->appointment_time
        );

        if (!$isAvailable) {
            return redirect()->back()->withInput()
                ->with('error', $unavailableReason ?? 'The doctor is not available at the selected date and time.');
        }

        $isTaken = Appointment::where('doctor_id', $request->doctor_id)
            ->where('appointment_date', $request->appointment_date)
            ->where('appointment_time', $request->appointment_time)
            ->whereNotIn('status', ['Rejected', 'Cancelled'])
            ->exists();

        if ($isTaken) {
            return redirect()->back()->withInput()
                ->with('error', 'This schedule is already taken. Please select another time slot.');
        }

        try {
            $appointment = Appointment::create([
                'patient_id'       => $patient->id,
                'doctor_id'        => $request->doctor_id,
                'appointment_date' => $request->appointment_date,
                'appointment_time' => $request->appointment_time,
                'reason'           => $request->reason,
                // Patient bookings ALWAYS start Pending (Staff approval),
                // follow-up or not.
                'status'           => 'Pending',
                'appointment_type' => $followUpLabRequest ? Appointment::TYPE_FOLLOW_UP : Appointment::TYPE_REGULAR,
                'follow_up_laboratory_request_id' => $followUpLabRequest?->id,
            ]);

            // ACTIVITY LOG
            UserLog::create([
                'user_id' => auth()->id(),
                'action'  => 'Created Appointment',
                'module'  => 'Appointments',
                'details' => ($followUpLabRequest ? 'Booked a follow-up consultation (laboratory result ' . $followUpLabRequest->reference_no . ') on ' : 'Booked an appointment on ') .
                             Carbon::parse($request->appointment_date)->format('F d, Y') .
                             ' at ' . Carbon::createFromFormat('H:i', $request->appointment_time)->format('h:i A') .
                             ' (Ref. No. ' . ($appointment->reference_no ?? 'N/A') . ')',
            ]);

        } catch (\Exception $e) {
            return redirect()->back()->withInput()
                ->with('error', 'Failed to create appointment. Please try again.');
        }

        return redirect()->route('patient.appointments.index')
            ->with('success', $followUpLabRequest
                ? 'Follow-up consultation booked! It is now waiting for clinic approval.'
                : 'Appointment added successfully!');
    }

    /**
     * Show a single appointment's details.
     *
     * Scoped to the logged-in patient's own appointments — attempting to
     * view another patient's appointment (e.g. by editing the URL) results
     * in the standard Laravel 404 behavior via findOrFail().
     */
    public function show($id)
    {
        $appointment = Appointment::with(['doctor', 'followUpLaboratoryRequest'])
            ->where('patient_id', auth()->id())
            ->findOrFail($id);

        return view('patient.appointment-details', [
            'appointment' => $appointment,
        ]);
    }

    /**
     * Patient cancels appointment.
     */
    public function cancel($id)
    {
        $appointment = Appointment::where('patient_id', auth()->id())
            ->findOrFail($id);

        if (!in_array($appointment->status, ['Pending', 'Approved', 'Rescheduled'])) {
            return redirect()->back()
                ->with('error', 'This appointment cannot be cancelled.');
        }

        if ($appointment->status === 'Approved') {
            $dateOnly            = Carbon::parse($appointment->appointment_date)->format('Y-m-d');
            $timeOnly            = Carbon::parse($appointment->appointment_time)->format('H:i:s');
            $appointmentDateTime = Carbon::parse($dateOnly . ' ' . $timeOnly);
            $minutesUntil        = Carbon::now()->diffInMinutes($appointmentDateTime, false);

            if ($minutesUntil <= 120) {
                return redirect()->back()
                    ->with('error', 'Approved appointments can no longer be cancelled within 2 hours of the scheduled time.');
            }
        }

        $appointment->update(['status' => 'Cancelled']);

        // ACTIVITY LOG
        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Cancelled Appointment',
            'module'  => 'Appointments',
            'details' => 'Cancelled appointment on ' .
                         Carbon::parse($appointment->appointment_date)->format('F d, Y') .
                         ' at ' . Carbon::parse($appointment->appointment_time)->format('h:i A') .
                         ' (Ref. No. ' . ($appointment->reference_no ?? 'N/A') . ')',
        ]);

        return redirect()->back()->with('success', 'Appointment cancelled successfully.');
    }
}
