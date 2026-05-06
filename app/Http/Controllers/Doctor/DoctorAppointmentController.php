<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\Review;

class DoctorAppointmentController extends Controller
{
    /**
     * Show appointments of logged-in doctor
     */
    public function index()
    {
        $doctor = auth()->user();

        $appointments = Appointment::with('patient')
            ->where('doctor_id', $doctor->id)
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->get();

        return view('doctor.appointments', compact('appointments'));
    }

    /**
     * Update appointment status
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string'
        ]);

        // SECURITY: restrict to doctor's own appointment
        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($id);

        $appointment->update([
            'status' => $request->status
        ]);

        return redirect()->back()->with('success', 'Appointment updated successfully!');
    }

    /**
     * Working page — view appointment + write prescriptions + submit review + save diagnosis
     */
    public function show($id)
    {
        // SECURITY: restrict to doctor's own appointment
        $appointment = Appointment::where('doctor_id', auth()->id())
            ->with('patient')
            ->findOrFail($id);

        $medicines = Medicine::where('status', '!=', 'Out of Stock')->get();

        $prescriptions = Prescription::where('appointment_id', $id)
            ->with('medicine')
            ->get();

        $review = Review::where('appointment_id', $id)->first();

        return view('doctor.show-appointment', compact(
            'appointment',
            'medicines',
            'prescriptions',
            'review'
        ));
    }

    /**
     * Save or update the diagnosis for an appointment
     */
    public function saveDiagnosis(Request $request, $id)
    {
        $request->validate([
            'diagnosis' => 'required|string|max:5000',
        ]);

        // SECURITY: restrict to doctor's own appointment
        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($id);

        if ($appointment->status === 'Completed') {
            return redirect()->back()->with('error', 'Cannot edit diagnosis of a completed appointment.');
        }

        $appointment->update([
            'diagnosis' => $request->diagnosis,
        ]);

        return redirect()->back()->with('success', 'Diagnosis saved successfully!');
    }

    /**
     * View-only printable medical report
     */
    public function report($id)
    {
        // SECURITY: restrict to doctor's own appointment
        $appointment = Appointment::where('doctor_id', auth()->id())
            ->with('patient')
            ->findOrFail($id);

        $prescriptions = Prescription::where('appointment_id', $id)
            ->with('medicine')
            ->get();

        $review = Review::where('appointment_id', $id)->first();

        return view('doctor.report', compact(
            'appointment',
            'prescriptions',
            'review'
        ));
    }

    /**
     * Doctor reschedules — auto-approved, logs who rescheduled
     */
    public function reschedule(Request $request, $id)
    {
        $request->validate([
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
        ]);

        // SECURITY: restrict to this doctor's appointment
        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($id);

        // BACKEND VALIDATION: prevent invalid reschedule
        if (!in_array($appointment->status, ['Approved', 'Rescheduled'])) {
            return redirect()->back()->with('error', 'This appointment cannot be rescheduled.');
        }

        // TIME SLOT CHECK
        $isTaken = Appointment::where('doctor_id', auth()->id())
            ->where('appointment_date', $request->appointment_date)
            ->where('appointment_time', $request->appointment_time)
            ->whereNotIn('status', ['Rejected', 'Cancelled', 'Completed'])
            ->where('id', '!=', $id)
            ->exists();

        if ($isTaken) {
            return redirect()->back()
                ->with('error', 'That time slot is already taken. Please choose another.');
        }

        $appointment->update([
            'appointment_date' => $request->appointment_date,
            'appointment_time' => $request->appointment_time,
            'status'           => 'Rescheduled',
            'rescheduled_by'   => 'doctor',
        ]);

        return redirect()->back()->with('success', 'Appointment rescheduled successfully!');
    }

    /**
     * Store or update review — automatically marks appointment as Completed
     */
    public function storeReview(Request $request)
    {
        $request->validate([
            'appointment_id'   => 'required|exists:appointments,id',
            'next_review_date' => 'nullable|date',
            'message'          => 'nullable|string',
        ]);

        // SECURITY: make sure doctor owns the appointment
        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($request->appointment_id);

        // Save or update review
        Review::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'next_review_date' => $request->next_review_date,
                'message'          => $request->message,
            ]
        );

        // Auto-complete the consultation once review is submitted
        if ($appointment->status !== 'Completed') {
            $appointment->update(['status' => 'Completed']);
        }

        return redirect()->back()->with('success', 'Consultation completed and review saved!');
    }
}