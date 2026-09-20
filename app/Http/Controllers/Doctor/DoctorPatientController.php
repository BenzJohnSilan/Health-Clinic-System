<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DoctorPatientController extends Controller
{
    /**
     * Show all patients (registered + walk-in merged), with search and
     * Patient Type filtering, paginated.
     */
    public function index(Request $request)
    {
        $search     = trim((string) $request->query('search', ''));
        $typeFilter = $request->query('type', 'all'); // all | registered | walkin

        if (!in_array($typeFilter, ['all', 'registered', 'walkin'], true)) {
            $typeFilter = 'all';
        }

        $registeredPatients = User::where('role', 'Patient')
            ->get()
            ->map(fn($user) => [
                'id'             => 'user_' . $user->id,
                'raw_id'         => $user->id,
                'type'           => 'user',
                // Same REG-/WLK- convention used elsewhere (see
                // Appointment::patientDisplayId()).
                'patient_id'     => 'REG-' . str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                'first_name'     => $user->first_name,
                'middle_name'    => $user->middle_name,
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
                'patient_id'     => 'WLK-' . str_pad((string) $patient->id, 5, '0', STR_PAD_LEFT),
                'first_name'     => $patient->first_name,
                'middle_name'    => $patient->middle_name,
                'last_name'      => $patient->last_name,
                'email'          => 'No email',
                'contact_number' => $patient->contact_number,
                'address'        => $patient->address,
                'is_walk_in'     => true,
            ]);

        $allPatients = $registeredPatients->concat($walkInPatients)->values();

        // ── Patient Type filter ─────────────────────────────────────────
        if ($typeFilter === 'registered') {
            $allPatients = $allPatients->where('is_walk_in', false)->values();
        } elseif ($typeFilter === 'walkin') {
            $allPatients = $allPatients->where('is_walk_in', true)->values();
        }

        // ── Search filter (name, middle name, contact number, patient ID) ─
        if ($search !== '') {
            $needle = mb_strtolower($search);

            $allPatients = $allPatients->filter(function ($p) use ($needle) {
                $haystacks = [
                    mb_strtolower(trim($p['first_name'] . ' ' . $p['last_name'])),
                    mb_strtolower((string) $p['first_name']),
                    mb_strtolower((string) ($p['middle_name'] ?? '')),
                    mb_strtolower((string) $p['last_name']),
                    mb_strtolower((string) ($p['contact_number'] ?? '')),
                    mb_strtolower($p['patient_id']),
                ];

                foreach ($haystacks as $haystack) {
                    if ($haystack !== '' && str_contains($haystack, $needle)) {
                        return true;
                    }
                }

                return false;
            })->values();
        }

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
        $patients->appends($request->query());

        return view('doctor.patient', [
            'patients'   => $patients,
            'search'     => $search,
            'typeFilter' => $typeFilter,
        ]);
    }

    /**
     * Show patient information and appointment history (paginated,
     * latest first), plus medical records / prescriptions for that
     * patient's full history.
     */
    public function showRecords(Request $request, string $id)
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

            $appointmentsQuery = Appointment::where('patient_id', $userModel->id)
                ->where('doctor_id', $doctorId);

            $allAppointmentIds = (clone $appointmentsQuery)->pluck('id');

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

            $appointmentsQuery = Appointment::where('walkin_patient_id', $walkIn->id)
                ->where('doctor_id', $doctorId);

            $allAppointmentIds = (clone $appointmentsQuery)->pluck('id');

            $records = MedicalRecord::whereIn('appointment_id', $allAppointmentIds)
                ->with('appointment')
                ->latest()
                ->get();
        }

        // Latest first, 5 per page.
        $appointments = (clone $appointmentsQuery)
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time')
            ->paginate(5)
            ->withQueryString();

        // Prescriptions across the patient's full appointment history
        // (not just the current page) — kept for parity with the
        // existing data the view previously received.
        $prescriptions = Prescription::whereIn('appointment_id', $allAppointmentIds)
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