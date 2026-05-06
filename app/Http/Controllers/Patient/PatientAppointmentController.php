<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;

class PatientAppointmentController extends Controller
{
    /**
     * Show patient appointments
     */
    public function index()
    {
        $patient = auth()->user();

        // Approved appointments for calendar
        $approvedAppointments = Appointment::with('doctor')
            ->where('patient_id', $patient->id)
            ->where('status', 'Approved')
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->get();

        // All appointments for listing
        $allAppointments = Appointment::with('doctor')
            ->where('patient_id', $patient->id)
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'asc')
            ->get();

        // Doctors list
        $doctors = User::where('role', 'Doctor')->get();

        // ================= GET BOOKED SLOTS =================
        $bookedSlots = Appointment::where('status', '!=', 'Rejected')
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

        // ================= VALIDATION =================
        $request->validate([
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required',
            'doctor_id'        => 'required|exists:users,id',
            'reason'           => 'required|string|max:255',
        ]);

        // ================= PREVENT PAST DATE + TIME =================
        $appointmentDateTime = Carbon::parse(
            $request->appointment_date . ' ' . $request->appointment_time
        );

        if ($appointmentDateTime->isPast()) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'You cannot book an appointment in the past.');
        }

        // ================= GLOBAL SLOT CHECK =================
        $isTaken = Appointment::where('doctor_id', $request->doctor_id)
            ->where('appointment_date', $request->appointment_date)
            ->where('appointment_time', $request->appointment_time)
            ->where('status', '!=', 'Rejected')
            ->exists();

        if ($isTaken) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'This schedule is already taken. Please select another time slot.');
        }

        // ================= CREATE APPOINTMENT =================
        try {
            Appointment::create([
                'patient_id'       => $patient->id,
                'doctor_id'        => $request->doctor_id,
                'appointment_date' => $request->appointment_date,
                'appointment_time' => $request->appointment_time,
                'reason'           => $request->reason,
                'status'           => 'Pending',
            ]);
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create appointment. Please try again.');
        }

        return redirect()
            ->route('patient.appointments.index')
            ->with('success', 'Appointment added successfully!');
    }

    /**
     * Patient cancels appointment.
     *
     * Allowed statuses : Pending, Approved, Rescheduled
     * Extra rule       : Approved appointments cannot be cancelled within 2 hours of the schedule.
     * Not allowed      : Rejected, Completed, Cancelled
     */
    public function cancel($id)
    {
        // Scope to this patient's appointment only
        $appointment = Appointment::where('patient_id', auth()->id())
            ->findOrFail($id);

        // ================= STATUS GATE =================
        if (!in_array($appointment->status, ['Pending', 'Approved', 'Rescheduled'])) {
            return redirect()->back()
                ->with('error', 'This appointment cannot be cancelled.');
        }

        // ================= 2-HOUR RULE (Approved only) =================
        if ($appointment->status === 'Approved') {
            // Parse date and time separately to avoid "double time" exception
            // when columns are stored as datetime (e.g. "2026-05-18 00:00:00")
            $dateOnly = Carbon::parse($appointment->appointment_date)->format('Y-m-d');
            $timeOnly = Carbon::parse($appointment->appointment_time)->format('H:i:s');
            $appointmentDateTime = Carbon::parse($dateOnly . ' ' . $timeOnly);

            // diffInMinutes returns negative if the appointment is already past
            $minutesUntil = Carbon::now()->diffInMinutes($appointmentDateTime, false);

            if ($minutesUntil <= 120) {
                return redirect()->back()
                    ->with('error', 'Approved appointments can no longer be cancelled within 2 hours of the scheduled time.');
            }
        }

        // ================= CANCEL =================
        $appointment->update([
            'status' => 'Cancelled',
        ]);

        return redirect()->back()->with('success', 'Appointment cancelled successfully.');
    }
}