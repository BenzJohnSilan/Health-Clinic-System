<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Support\Facades\Mail;
use App\Mail\AppointmentStatusMail;

class AdminAppointmentController extends Controller
{
    /**
     * Display ONLY approved appointments.
     */
    public function index()
    {
        $appointments = Appointment::with(['patient', 'doctor'])
            ->where('status', 'Approved')
            ->latest()
            ->get();

        $patients = User::where('role', 'patient')->get();
        $doctors  = User::where('role', 'doctor')->get();

        return view('admin.appointments', compact('appointments', 'patients', 'doctors'));
    }

    /**
     * Display all pending appointments.
     */
    public function pending()
    {
        $appointments = Appointment::with(['patient', 'doctor'])
            ->where('status', 'Pending')
            ->latest()
            ->get();

        return view('admin.pending-appointments', compact('appointments'));
    }

    /**
     * Store a newly created appointment.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id'       => 'required|exists:users,id',
            'doctor_id'        => 'required|exists:users,id',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required',
            'status'           => 'required|string',
            'reason'           => 'nullable|string',
        ]);

        $appointment = Appointment::create($validated);

        // ================= LOG =================
        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Created Appointment',
            'details' => 'Appointment ID: ' . $appointment->id
        ]);

        return redirect()->route('admin.appointments.index')
                         ->with('success', 'Appointment added successfully!');
    }

    /**
     * Show edit form.
     */
    public function edit($id)
    {
        $appointment = Appointment::findOrFail($id);

        $patients = User::where('role', 'patient')->get();
        $doctors  = User::where('role', 'doctor')->get();

        return view('admin.edit-appointment', compact('appointment', 'patients', 'doctors'));
    }

    /**
     * Update appointment.
     */
    public function update(Request $request, $id)
    {
        $appointment = Appointment::findOrFail($id);

        $validated = $request->validate([
            'patient_id'       => 'required|exists:users,id',
            'doctor_id'        => 'required|exists:users,id',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required',
            'status'           => 'required|string',
            'reason'           => 'nullable|string',
        ]);

        $appointment->update($validated);

        // ================= LOG =================
        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Updated Appointment',
            'details' => 'Appointment ID: ' . $appointment->id
        ]);

        return redirect()->route('admin.appointments.index')
                         ->with('success', 'Appointment updated successfully!');
    }

    /**
     * Delete appointment.
     */
    public function destroy($id)
    {
        $appointment = Appointment::findOrFail($id);

        // ================= LOG =================
        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Deleted Appointment',
            'details' => 'Appointment ID: ' . $appointment->id
        ]);

        $appointment->delete();

        return redirect()->route('admin.appointments.index')
                         ->with('success', 'Appointment deleted successfully!');
    }

    /**
     * Approve appointment + EMAIL
     */
    public function approve($id)
    {
        $appointment = Appointment::with(['patient', 'doctor'])->findOrFail($id);

        $appointment->update([
            'status' => 'Approved'
        ]);

        // ================= LOG =================
        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Approved Appointment',
            'details' => 'Appointment ID: ' . $appointment->id
        ]);

        // SEND EMAIL
        Mail::to($appointment->patient->email)
            ->send(new AppointmentStatusMail($appointment, 'Approved'));

        return redirect()->back()->with('success', 'Appointment approved successfully!');
    }

    /**
     * Reject appointment WITH reason + EMAIL
     */
    public function reject(Request $request, $id)
    {
        $appointment = Appointment::with(['patient', 'doctor'])->findOrFail($id);

        $request->validate([
            'reason' => 'required|string|max:255'
        ]);

        $appointment->update([
            'status' => 'Rejected',
            'reason' => $request->reason
        ]);

        // ================= LOG =================
        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Rejected Appointment',
            'details' => 'Appointment ID: ' . $appointment->id . ' | Reason: ' . $request->reason
        ]);

        // SEND EMAIL
        Mail::to($appointment->patient->email)
            ->send(new AppointmentStatusMail($appointment, 'Rejected'));

        return redirect()->back()->with('success', 'Appointment rejected successfully!');
    }
}