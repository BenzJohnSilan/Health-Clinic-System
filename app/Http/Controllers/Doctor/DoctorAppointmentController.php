<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\Medicine;
use App\Models\MedicalRecord;
use App\Models\Prescription;
use App\Models\Review;
use Carbon\Carbon;

class DoctorAppointmentController extends Controller
{
    public function index()
    {
        $doctor = auth()->user();

        $appointments = Appointment::with(['patient', 'walkinPatient'])
            ->where('doctor_id', $doctor->id)
            ->whereIn('status', ['Approved', 'Rescheduled'])
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->paginate(10);

        return view('doctor.appointments', compact('appointments'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string'
        ]);

        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($id);

        $appointment->update([
            'status' => $request->status
        ]);

        return redirect()->back()->with('success', 'Appointment updated successfully!');
    }

    public function show($id)
    {
        $appointment = Appointment::where('doctor_id', auth()->id())
            ->with(['patient', 'walkinPatient'])
            ->findOrFail($id);

        $appointmentDate = Carbon::parse($appointment->appointment_date)->startOfDay();
        $today           = Carbon::today();

        if (!$appointmentDate->equalTo($today)) {
            return redirect()
                ->route('doctor.appointments.index')
                ->with('error', 'You can only consult on the scheduled date.');
        }

        $medicines = Medicine::where('status', '!=', 'Out of Stock')->get();

        $prescriptions = Prescription::where('appointment_id', $id)
            ->with('medicine')
            ->get();

        $review = Review::where('appointment_id', $id)->first();

        $medicalRecord = MedicalRecord::where('appointment_id', $id)->first();

        return view('doctor.show-appointment', compact(
            'appointment',
            'medicines',
            'prescriptions',
            'review',
            'medicalRecord'
        ));
    }

    public function saveDiagnosis(Request $request, $id)
    {
        $request->validate([
            'chief_complaint' => 'nullable|string|max:5000',
            'diagnosis'       => 'required|string|max:5000',
            'treatment'       => 'nullable|string|max:5000',
            'notes'           => 'nullable|string|max:5000',
            'blood_pressure'  => 'nullable|string|max:50',
            'temperature'     => 'nullable|string|max:50',
            'weight'          => 'nullable|string|max:50',
            'height'          => 'nullable|string|max:50',
        ]);

        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($id);

        $appointmentDate = Carbon::parse($appointment->appointment_date)->startOfDay();
        if (!$appointmentDate->equalTo(Carbon::today())) {
            return redirect()
                ->route('doctor.appointments.index')
                ->with('error', 'You can only consult on the scheduled date.');
        }

        if ($appointment->status === 'Completed') {
            return redirect()->back()->with('error', 'Cannot edit medical record of a completed appointment.');
        }

        MedicalRecord::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'patient_id'      => $appointment->patient_id,
                'doctor_id'       => $appointment->doctor_id,
                'chief_complaint' => $request->chief_complaint,
                'diagnosis'       => $request->diagnosis,
                'treatment'       => $request->treatment,
                'notes'           => $request->notes,
                'blood_pressure'  => $request->blood_pressure,
                'temperature'     => $request->temperature,
                'weight'          => $request->weight,
                'height'          => $request->height,
            ]
        );

        return redirect()->back()->with('success', 'Medical record saved successfully!');
    }

    public function report($id)
    {
        $appointment = Appointment::where('doctor_id', auth()->id())
            ->with('patient')
            ->findOrFail($id);

        $prescriptions = Prescription::where('appointment_id', $id)
            ->with('medicine')
            ->get();

        $review = Review::where('appointment_id', $id)->first();

        $medicalRecord = MedicalRecord::where('appointment_id', $id)->first();

        return view('doctor.report', compact(
            'appointment',
            'prescriptions',
            'review',
            'medicalRecord'
        ));
    }

    public function reschedule(Request $request, $id)
    {
        $request->validate([
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
        ]);

        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($id);

        if (!in_array($appointment->status, ['Approved', 'Rescheduled'])) {
            return redirect()->back()->with('error', 'This appointment cannot be rescheduled.');
        }

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
            'rescheduled_by'   => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Appointment rescheduled successfully!');
    }

    public function storeReview(Request $request)
    {
        $request->validate([
            'appointment_id'   => 'required|exists:appointments,id',
            'next_review_date' => 'nullable|date',
            'message'          => 'nullable|string',
        ]);

        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($request->appointment_id);

        $appointmentDate = Carbon::parse($appointment->appointment_date)->startOfDay();
        if (!$appointmentDate->equalTo(Carbon::today())) {
            return redirect()
                ->route('doctor.appointments.index')
                ->with('error', 'You can only consult on the scheduled date.');
        }

        Review::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'next_review_date' => $request->next_review_date,
                'message'          => $request->message,
            ]
        );

        if ($appointment->status !== 'Completed') {
            $appointment->update(['status' => 'Completed']);
        }

        return redirect()->back()->with('success', 'Consultation completed and review saved!');
    }

    /**
     * ─── THIS IS THE MISSING METHOD ───────────────────────────────────────────
     * Lists all medical records for the logged-in doctor.
     * Passes $records to the index blade view.
     */
    public function medicalRecordsIndex()
    {
        $records = MedicalRecord::with(['appointment.patient', 'appointment.walkinPatient'])
            ->where('doctor_id', auth()->id())
            ->latest()
            ->paginate(10); 

        return view('doctor.medical-records', compact('records'));
    }

    /**
     * Show medical record (view-only)
     */
    public function showMedicalRecord($appointmentId)
    {
        $appointment = Appointment::with([
            'patient',
            'walkinPatient',
            'medicalRecord',
            'prescriptions.medicine',
            'review'
        ])->findOrFail($appointmentId);

        $medicalRecord = MedicalRecord::where('appointment_id', $appointmentId)->first();

        $prescriptions = Prescription::where('appointment_id', $appointmentId)
            ->with('medicine')
            ->get();

        $review = Review::where('appointment_id', $appointmentId)->first();

        // ← changed from 'doctor.medical-records' to 'doctor.medical-record-show'
        return view('doctor.medical-record-show', compact(
            'appointment',
            'medicalRecord',
            'prescriptions',
            'review',
        ));
    }

    public function printMedicalCertificate($appointmentId)
    {
        $appointment = Appointment::with([
            'patient',
            'walkinPatient',
            'doctor',
        ])->where('doctor_id', auth()->id())
        ->findOrFail($appointmentId);

        $medicalRecord = MedicalRecord::where('appointment_id', $appointmentId)->first();

        $isWalkIn   = $appointment->walkin_patient_id !== null;
        $patientObj = $isWalkIn ? $appointment->walkinPatient : $appointment->patient;

        return view('doctor.medical-certificate-print', compact(
            'appointment',
            'medicalRecord',
            'isWalkIn',
            'patientObj',
        ));
    }
}