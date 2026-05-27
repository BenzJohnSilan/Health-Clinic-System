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
    public function store(Request $request)
    {
        $isManual = $request->medicine_id === 'manual';

        $request->validate([
            'appointment_id'      => 'required',
            'medicine_id'         => 'required',
            'dosage'              => 'required|string',
            'frequency'           => 'required|string',
            'duration'            => 'required|string',

            // Only required when NOT manual
            'quantity_prescribed' => $isManual
                                        ? 'nullable|integer|min:1'
                                        : 'required|integer|min:1',
        ]);

        // =====================================================
        // MANUAL PRESCRIPTION
        // =====================================================
        if ($isManual) {

            $request->validate([
                'manual_medicine_name' => 'required|string|max:255',
            ]);

            Prescription::create([
                'appointment_id'       => $request->appointment_id,
                'medicine_id'          => null,
                'manual_medicine_name' => $request->manual_medicine_name,
                'dosage'               => $request->dosage,
                'frequency'            => $request->frequency,
                'duration'             => $request->duration,
                'quantity_prescribed'  => $request->quantity_prescribed ?? 0,
            ]);

            return back()->with(
                'success',
                'Manual prescription added successfully!'
            );
        }

        // =====================================================
        // NORMAL MEDICINE FLOW
        // =====================================================
        $medicine = Medicine::findOrFail($request->medicine_id);

        // Check stock
        if ($medicine->quantity < $request->quantity_prescribed) {

            return back()->with(
                'error',
                'Not enough stock for this medicine!'
            );
        }

        // Save prescription
        Prescription::create([
            'appointment_id'      => $request->appointment_id,
            'medicine_id'         => $medicine->id,
            'dosage'              => $request->dosage,
            'frequency'           => $request->frequency,
            'duration'            => $request->duration,
            'quantity_prescribed' => $request->quantity_prescribed,
        ]);

        // Deduct stock
        $medicine->quantity -= $request->quantity_prescribed;

        // Update status
        $medicine->status = match (true) {

            $medicine->quantity <= 0
                => 'Out of Stock',

            $medicine->quantity <= 10
                => 'Low Stock',

            default
                => 'Available',
        };

        $medicine->save();

        return back()->with(
            'success',
            'Prescription added successfully!'
        );
    }

    public function destroy(Prescription $prescription)
    {
        // =====================================================
        // RESTORE STOCK (if normal medicine)
        // =====================================================
        if ($prescription->medicine_id) {

            $medicine = $prescription->medicine;

            if ($medicine) {

                $medicine->quantity +=
                    $prescription->quantity_prescribed;

                $medicine->status = match (true) {

                    $medicine->quantity <= 0
                        => 'Out of Stock',

                    $medicine->quantity <= 10
                        => 'Low Stock',

                    default
                        => 'Available',
                };

                $medicine->save();
            }
        }

        $prescription->delete();

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