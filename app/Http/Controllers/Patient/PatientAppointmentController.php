<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\User;

class PatientAppointmentController extends Controller
{
    /**
     * Show patient appointments
     */
    public function index()
    {
        $patient = auth()->user();

        // Fetch approved appointments for calendar
        $approvedAppointments = Appointment::with('doctor')
            ->where('patient_id', $patient->id)
            ->where('status', 'Approved') // Only approved show on calendar
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->get();

        // Fetch all appointments for listing if needed
        $allAppointments = Appointment::with('doctor')
            ->where('patient_id', $patient->id)
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'asc')
            ->get();

        // Fetch all doctors for dropdown
        $doctors = User::where('role', 'Doctor')->get();

        return view('patient.appointments', [
            'doctors' => $doctors,
            'upcomingAppointments' => $approvedAppointments, // For calendar
            'appointments' => $allAppointments, // For listing
        ]);
    }

    /**
     * Store new appointment
     */
    public function store(Request $request)
    {
        $patient = auth()->user();

        // Validate input including time
        $request->validate([
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required',
            'doctor_id' => 'required|exists:users,id',
            'reason' => 'required|string|max:255',
        ]);

        // ===================== DUPLICATE CHECK =====================
        // Only prevent duplicates for registered patients
        if(!$patient->is_walk_in) {
            $exists = Appointment::where('patient_id', $patient->id)
                ->where('doctor_id', $request->doctor_id)
                ->where('appointment_date', $request->appointment_date)
                ->where('appointment_time', $request->appointment_time)
                ->exists();

            if($exists){
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'You already have an appointment with this doctor at this date and time.');
            }
        }

        // Create new appointment
        Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $request->doctor_id,
            'appointment_date' => $request->appointment_date,
            'appointment_time' => $request->appointment_time,
            'reason' => $request->reason,
            'status' => 'Pending', // default status
        ]);

        return redirect()
            ->route('patient.appointments.index')
            ->with('success', 'Appointment added successfully!');
    }
}