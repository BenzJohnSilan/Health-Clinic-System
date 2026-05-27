<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\User;
use App\Models\UserLog;
use Carbon\Carbon;

class PatientAppointmentController extends Controller
{
    /**
     * Show patient appointments
     */
    public function index()
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
            ->whereIn('status', ['Approved', 'Pending', 'Rescheduled'])
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'asc')
            ->paginate(10);

        $doctors = User::where('role', 'Doctor')->get();

        $bookedSlots = Appointment::whereNotIn('status', ['Rejected', 'Cancelled'])
            ->get(['doctor_id', 'appointment_date', 'appointment_time']);

        return view('patient.appointments', [
            'doctors'              => $doctors,
            'upcomingAppointments' => $approvedAppointments,
            'appointments'         => $allAppointments,
            'bookedSlots'          => $bookedSlots,
        ]);
    }

    /**
     * Store new appointment
     */
    public function store(Request $request)
    {
        $patient = auth()->user();

        $request->validate([
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required',
            'doctor_id'        => 'required|exists:users,id',
            'reason'           => 'required|string|max:255',
        ]);

        $appointmentDateTime = Carbon::parse(
            $request->appointment_date . ' ' . $request->appointment_time
        );

        if ($appointmentDateTime->isPast()) {
            return redirect()->back()->withInput()
                ->with('error', 'You cannot book an appointment in the past.');
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
                'status'           => 'Pending',
            ]);

            // ACTIVITY LOG
            UserLog::create([
                'user_id' => auth()->id(),
                'action'  => 'Created Appointment',
                'details' => 'Booked an appointment on ' .
                             Carbon::parse($request->appointment_date)->format('F d, Y') .
                             ' at ' . Carbon::createFromFormat('H:i', $request->appointment_time)->format('h:i A') .
                             ' (Ref. No. ' . ($appointment->reference_no ?? 'N/A') . ')',
            ]);

        } catch (\Exception $e) {
            return redirect()->back()->withInput()
                ->with('error', 'Failed to create appointment. Please try again.');
        }

        return redirect()->route('patient.appointments.index')
            ->with('success', 'Appointment added successfully!');
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
            'details' => 'Cancelled appointment on ' .
                         Carbon::parse($appointment->appointment_date)->format('F d, Y') .
                         ' at ' . Carbon::parse($appointment->appointment_time)->format('h:i A') .
                         ' (Ref. No. ' . ($appointment->reference_no ?? 'N/A') . ')',
        ]);

        return redirect()->back()->with('success', 'Appointment cancelled successfully.');
    }
}