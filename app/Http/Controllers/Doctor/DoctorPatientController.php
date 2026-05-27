<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DoctorPatientController extends Controller
{
    /**
     * Show all patients (registered + walk-in merged) with pagination
     */
    public function index()
    {
        $registeredPatients = User::where('role', 'Patient')
            ->get()
            ->map(fn($user) => [
                'id'             => 'user_' . $user->id,
                'raw_id'         => $user->id,
                'type'           => 'user',
                'first_name'     => $user->first_name,
                'last_name'      => $user->last_name,
                'email'          => $user->email,
                'contact_number' => $user->contact_number,
                'address'        => $user->address,
                'is_walk_in'     => false,
            ]);

        $walkInPatients = Patient::where('is_walk_in', true)
            ->get()
            ->map(fn($patient) => [
                'id'             => 'patient_' . $patient->id,
                'raw_id'         => $patient->id,
                'type'           => 'patient',
                'first_name'     => $patient->first_name,
                'last_name'      => $patient->last_name,
                'email'          => 'No email',
                'contact_number' => $patient->contact_number,
                'address'        => $patient->address,
                'is_walk_in'     => true,
            ]);

        $allPatients = $registeredPatients->concat($walkInPatients)->values();

        // Manual pagination
        $perPage     = 10;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $pagedItems  = $allPatients->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $patients = new LengthAwarePaginator(
            $pagedItems,
            $allPatients->count(),
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );

        return view('doctor.patient', compact('patients'));
    }

    /**
     * Show patient medical records, appointments, and prescriptions
     */
    public function showRecords(string $id)
    {
        [$type, $rawId] = explode('_', $id, 2);

        $doctorId = auth()->id();

        if ($type === 'user') {
            // ── Registered patient ──────────────────────────────────────
            $userModel = User::findOrFail($rawId);

            $patient = [
                'first_name'     => $userModel->first_name,
                'last_name'      => $userModel->last_name,
                'age'            => $userModel->age            ?? null,
                'gender'         => $userModel->gender         ?? null,
                'contact_number' => $userModel->contact_number ?? null,
                'address'        => $userModel->address        ?? null,
                'is_walk_in'     => false,
            ];

            $appointments = Appointment::where('patient_id', $userModel->id)
                ->where('doctor_id', $doctorId)
                ->latest()
                ->get();

            $records = MedicalRecord::where('patient_id', $userModel->id)
                ->with('appointment')
                ->latest()
                ->get();

        } else {
            // ── Walk-in patient ──────────────────────────────────────────
            $walkIn = Patient::findOrFail($rawId);

            $patient = [
                'first_name'     => $walkIn->first_name,
                'last_name'      => $walkIn->last_name,
                'age'            => $walkIn->age            ?? null,
                'gender'         => $walkIn->gender         ?? null,
                'contact_number' => $walkIn->contact_number ?? null,
                'address'        => $walkIn->address        ?? null,
                'is_walk_in'     => true,
            ];

            $appointments = Appointment::where('walkin_patient_id', $walkIn->id)
                ->where('doctor_id', $doctorId)
                ->latest()
                ->get();

            $appointmentIds = $appointments->pluck('id');

            $records = MedicalRecord::whereIn('appointment_id', $appointmentIds)
                ->with('appointment')
                ->latest()
                ->get();
        }

        // Collect all appointment IDs to fetch prescriptions in one query
        $appointmentIds = $appointments->pluck('id');

        $prescriptions = Prescription::whereIn('appointment_id', $appointmentIds)
            ->with('medicine')
            ->get()
            ->map(function ($prescription) {
                $prescription->medicine_name =
                    $prescription->medicine?->medicine_name
                    ?? $prescription->manual_medicine_name
                    ?? 'Unknown';

                return $prescription;
            });

        return view('doctor.patient-records', compact(
            'patient',
            'appointments',
            'records',
            'prescriptions'
        ));
    }
}