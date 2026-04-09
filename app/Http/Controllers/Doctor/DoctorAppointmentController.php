<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;

class DoctorAppointmentController extends Controller
{
    public function index()
    {
        $doctor = auth()->user();

        $appointments = Appointment::with('patient')
            ->where('doctor_id', $doctor->id)
            ->where('status', 'Approved')
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->get();

        return view('doctor.appointments', compact('appointments'));
    }

    public function update(Request $request, $id)
    {
        $appointment = Appointment::findOrFail($id);

        $request->validate([
            'status' => 'required|string'
        ]);

        $appointment->update([
            'status' => $request->status
        ]);

        return redirect()->back()->with('success', 'Appointment updated successfully!');
    }
}
