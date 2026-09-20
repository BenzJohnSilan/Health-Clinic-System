<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Models\Medicine;
use App\Models\Appointment;
use App\Models\Review;
use Illuminate\Http\Request;

class PrescriptionController extends Controller
{
    /**
     * Doctor creates a prescription. This ONLY records what was
     * written — it never touches medicine inventory. Stock is deducted
     * exclusively by StaffPrescriptionController::dispense() once
     * Staff actually releases the medicine (see Section 10 of the
     * workflow spec: "The Doctor must not manage or deduct medicine
     * inventory").
     */
    public function store(Request $request)
    {
        $isManual = $request->medicine_id === 'manual';

        $request->validate([
            'appointment_id'      => 'required|exists:appointments,id',
            'medicine_id'         => 'required',
            'dosage'              => 'required|string',
            'frequency'           => 'required|string',
            'duration'            => 'required|string',
            'instructions'        => 'nullable|string|max:255',

            // Only required when NOT manual
            'quantity_prescribed' => $isManual
                                        ? 'nullable|integer|min:1'
                                        : 'required|integer|min:1',
        ]);

        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($request->appointment_id);

        // A prescription may only be written while the consultation is
        // actively In Progress — matches the same window the
        // consultation form itself is editable in.
        if ($appointment->status !== 'In Progress') {
            return back()->with('error', 'Prescriptions can only be added while the consultation is In Progress.');
        }

        // =====================================================
        // MANUAL PRESCRIPTION (no inventory link — never billed,
        // never dispensable against stock)
        // =====================================================
        if ($isManual) {

            $request->validate([
                'manual_medicine_name' => 'required|string|max:255',
            ]);

            Prescription::create([
                'appointment_id'       => $appointment->id,
                'medicine_id'          => null,
                'manual_medicine_name' => $request->manual_medicine_name,
                'dosage'               => $request->dosage,
                'frequency'            => $request->frequency,
                'duration'             => $request->duration,
                'instructions'         => $request->instructions,
                'quantity_prescribed'  => $request->quantity_prescribed ?? 0,
            ]);

            // Stay on Step 3 (Prescription & Review) — never bounce back
            // to Step 1 after adding a prescription.
            session(["consultation_step_{$appointment->id}" => 3]);

            return back()->with(
                'success',
                'Manual prescription added successfully!'
            );
        }

        // =====================================================
        // NORMAL MEDICINE FLOW — no stock check here. Availability is
        // checked at dispensing time by Staff, who can also see the
        // live stock number before deciding whether/how much to
        // release.
        // =====================================================
        $medicine = Medicine::findOrFail($request->medicine_id);

        Prescription::create([
            'appointment_id'      => $appointment->id,
            'medicine_id'         => $medicine->id,
            'dosage'              => $request->dosage,
            'frequency'           => $request->frequency,
            'duration'            => $request->duration,
            'instructions'        => $request->instructions,
            'quantity_prescribed' => $request->quantity_prescribed,
        ]);

        session(["consultation_step_{$appointment->id}" => 3]);

        return back()->with(
            'success',
            'Prescription added successfully!'
        );
    }

    /**
     * Delete a prescription the Doctor hasn't finalized yet. Never
     * touches inventory (creation no longer deducts it either). Blocked
     * once Staff has already dispensed it — deleting a dispensed
     * prescription would erase the only record of medicine that has
     * physically left the clinic.
     */
    public function destroy(Prescription $prescription)
    {
        if ($prescription->dispense_status === 'Dispensed') {
            return back()->with('error', 'This prescription has already been dispensed and can no longer be deleted.');
        }

        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($prescription->appointment_id);

        if ($appointment->status !== 'In Progress') {
            return back()->with('error', 'Prescriptions can only be deleted while the consultation is In Progress.');
        }

        $prescription->delete();

        session(["consultation_step_{$appointment->id}" => 3]);

        return back()->with(
            'success',
            'Prescription deleted successfully!'
        );
    }

    // =====================================================
    // PRINT PRESCRIPTION
    // =====================================================
    public function print($appointmentId)
    {
        $appointment = Appointment::with([
            'doctor',
            'patient',
            'walkinPatient',
            'prescriptions.medicine'
        ])->findOrFail($appointmentId);

        $prescriptions = Prescription::where(
            'appointment_id',
            $appointmentId
        )->get();

        $review = Review::where('appointment_id', $appointmentId)->first();

        return view(
            'doctor.print-prescription',
            compact(
                'appointment',
                'prescriptions',
                'review' 
            )
        );
    }
}
