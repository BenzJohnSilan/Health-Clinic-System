<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Appointment;
use App\Models\UserLog;
use Illuminate\Support\Facades\Mail;
use App\Mail\AppointmentStatusMail;

class StaffAppointmentController extends Controller
{
    // ================= LIST ALL APPOINTMENTS =================
    public function index()
    {
        $appointments = Appointment::with(['patient', 'walkinPatient', 'doctor'])
            ->latest()
            ->paginate(10);

        return view('staff.appointments', compact('appointments'));
    }

    // ================= LIST PENDING APPOINTMENTS =================
    public function pending()
    {
        $appointments = Appointment::with(['patient', 'walkinPatient', 'doctor'])
            ->where('status', 'Pending')
            ->latest()
            ->paginate(10);

        return view('staff.pending-appointments', compact('appointments'));
    }

    // ================= SHOW APPOINTMENT DETAILS =================
    public function show(Appointment $appointment)
    {
        $appointment->load(['patient', 'walkinPatient', 'doctor']);

        return view('staff.appointment-show', compact('appointment'));
    }

    // ================= STORE APPOINTMENT =================
    public function store(Request $request)
    {
        $request->validate([
            'patient_id'       => 'required',
            'patient_type'     => 'required|in:user,patient',
            'doctor_id'        => 'required|exists:users,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required',
            'reason'           => 'required|string|max:500',
        ]);

        // ── Duplicate slot check ──────────────────────────────────────────
        // Prevent double-booking the same doctor at the same date and time.
        $conflict = Appointment::where('doctor_id',        $request->doctor_id)
            ->where('appointment_date', $request->appointment_date)
            ->where('appointment_time', $request->appointment_time)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'appointment_time' => 'This doctor already has an appointment at the selected date and time. Please choose a different time slot.',
            ]);
        }
        // ─────────────────────────────────────────────────────────────────

        $data = [
            'doctor_id'         => $request->doctor_id,
            'appointment_date'  => $request->appointment_date,
            'appointment_time'  => $request->appointment_time,
            'reason'            => $request->reason,
            'status'            => 'Approved',
            'patient_id'        => null,
            'walkin_patient_id' => null,
        ];

        if ($request->patient_type === 'user') {
            $data['patient_id'] = $request->patient_id;
        } else {
            $data['walkin_patient_id'] = $request->patient_id;
        }

        $appointment = Appointment::create($data);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Created Appointment',
            'details' => 'Appointment ID: ' . $appointment->id
                       . ' | Type: '        . $request->patient_type
                       . ' | Status: Approved',
        ]);

        return redirect()->route('staff.appointments.index')
            ->with('success', 'Appointment scheduled successfully!');
    }

    // ================= APPROVE APPOINTMENT =================
    public function approve($id)
    {
        $appointment = Appointment::with(['patient', 'walkinPatient', 'doctor'])->findOrFail($id);

        $appointment->update(['status' => 'Approved']);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Approved Appointment',
            'details' => 'Appointment ID: ' . $appointment->id,
        ]);

        if ($appointment->patient && $appointment->patient->email) {
            Mail::to($appointment->patient->email)
                ->send(new AppointmentStatusMail($appointment, 'Approved'));
        }

        return redirect()->back()->with('success', 'Appointment approved successfully!');
    }

    // ================= REJECT APPOINTMENT =================
    public function reject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $appointment = Appointment::with(['patient', 'walkinPatient', 'doctor'])->findOrFail($id);

        $appointment->update([
            'status' => 'Rejected',
            'reason' => $request->reason,
        ]);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Rejected Appointment',
            'details' => 'Appointment ID: ' . $appointment->id
                       . ' | Reason: '      . $request->reason,
        ]);

        if ($appointment->patient && $appointment->patient->email) {
            Mail::to($appointment->patient->email)
                ->send(new AppointmentStatusMail($appointment, 'Rejected'));
        }

        return redirect()->back()->with('success', 'Appointment rejected successfully!');
    }

    // ================= CANCEL APPOINTMENT =================
    public function cancel($id)
    {
        $appointment = Appointment::with(['patient', 'walkinPatient', 'doctor'])->findOrFail($id);

        $appointment->update(['status' => 'Cancelled']);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Cancelled Appointment',
            'details' => 'Appointment ID: ' . $appointment->id,
        ]);

        if ($appointment->patient && $appointment->patient->email) {
            Mail::to($appointment->patient->email)
                ->send(new AppointmentStatusMail($appointment, 'Cancelled'));
        }

        return redirect()->back()->with('success', 'Appointment cancelled successfully!');
    }
}