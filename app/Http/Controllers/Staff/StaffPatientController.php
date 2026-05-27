<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\User;
use App\Models\Patient;
use App\Models\Appointment;
use Carbon\Carbon;

class StaffPatientController extends Controller
{
    // ================= LIST ALL PATIENTS =================
    public function index(Request $request)
    {
        // Registered patients (from users table)
        $registeredPatients = User::where('role', 'Patient')
            ->get()
            ->map(function ($user) {
                return [
                    'id'                => 'user_' . $user->id,
                    'raw_id'            => $user->id,
                    'type'              => 'user',
                    'first_name'        => $user->first_name,
                    'middle_name'       => $user->middle_name ?? '',
                    'last_name'         => $user->last_name,
                    'suffix'            => $user->suffix ?? '',
                    'birthdate'         => $user->birthdate ?? '',
                    'age'               => $user->birthdate
                                              ? Carbon::parse($user->birthdate)->age
                                              : '-',
                    'gender'            => $user->gender ?? '',
                    'civil_status'      => $user->civil_status ?? '',
                    'contact_number'    => $user->contact_number ?? '',
                    'address'           => $user->address ?? '',
                    'blood_type'        => $user->blood_type ?? '',
                    'allergies'         => $user->allergies ?? '',
                    'emergency_name'    => $user->emergency_name ?? '',
                    'emergency_contact' => $user->emergency_contact ?? '',
                    'relationship'      => $user->relationship ?? '',
                    'emergency_address' => $user->emergency_address ?? '',
                    'email'             => $user->email ?? '',
                    'username'          => $user->username ?? '',
                    'status'            => $user->status ?? '',
                    'approval'          => $user->approval_status ?? '',
                    'is_walk_in'        => false,
                ];
            });

        // Walk-in patients (from patients table)
        $walkInPatients = Patient::where('is_walk_in', true)
            ->get()
            ->map(function ($patient) {
                return [
                    'id'                => 'patient_' . $patient->id,
                    'raw_id'            => $patient->id,
                    'type'              => 'patient',
                    'first_name'        => $patient->first_name,
                    'middle_name'       => $patient->middle_name ?? '',
                    'last_name'         => $patient->last_name,
                    'suffix'            => $patient->suffix ?? '',
                    'birthdate'         => $patient->birthdate ?? '',
                    'age'               => $patient->birthdate
                                              ? Carbon::parse($patient->birthdate)->age
                                              : '-',
                    'gender'            => $patient->gender ?? '',
                    'civil_status'      => $patient->civil_status ?? '',
                    'contact_number'    => $patient->contact_number ?? '',
                    'address'           => $patient->address ?? '',
                    'blood_type'        => $patient->blood_type ?? '',
                    'allergies'         => $patient->allergies ?? '',
                    'emergency_name'    => $patient->emergency_name ?? '',
                    'emergency_contact' => $patient->emergency_contact ?? '',
                    'relationship'      => $patient->relationship ?? '',
                    'emergency_address' => $patient->emergency_address ?? '',
                    // Walk-in patients have no account fields
                    'email'             => '',
                    'username'          => '',
                    'status'            => '',
                    'approval'          => '',
                    'is_walk_in'        => true,
                ];
            });

        // Merge & sort
        $allPatients = $registeredPatients
            ->concat($walkInPatients)
            ->sortBy('first_name')
            ->values();

        // Manual pagination
        $perPage     = 10;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $pageItems   = $allPatients->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $patients = new LengthAwarePaginator(
            $pageItems,
            $allPatients->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Doctors
        $doctors = User::where('role', 'Doctor')
            ->orderBy('last_name')
            ->get();

        $bookedSlots = Appointment::select(
            'doctor_id',
            'appointment_date',
            'appointment_time'
        )
        ->whereNotIn('status', ['Cancelled', 'Rejected'])
        ->get();
        return view('staff.patient', compact(
            'patients',
            'doctors',
            'bookedSlots'
        ));
    }

    // ================= STORE WALK-IN PATIENT =================
    public function store(Request $request)
    {
        $request->validate([
            // Personal
            'first_name'        => 'required|string|max:50',
            'middle_name'       => 'nullable|string|max:50',
            'last_name'         => 'required|string|max:50',
            'suffix'            => 'nullable|string|max:10',
            'birthdate'         => 'nullable|date',
            'gender'            => 'required|in:Male,Female,Other',
            'civil_status'      => 'required|in:Single,Married,Widowed,Separated',
            'contact_number'    => 'nullable|string|max:11',
            'address'           => 'nullable|string|max:255',
            // Medical
            'blood_type'        => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'allergies'         => 'nullable|string',
            // Emergency Contact
            'emergency_name'    => 'nullable|string|max:100',
            'emergency_contact' => 'nullable|string|max:11',
            'relationship'      => 'nullable|string|max:50',
            'emergency_address' => 'nullable|string|max:255',
        ]);

        Patient::create([
            'first_name'        => $request->first_name,
            'middle_name'       => $request->middle_name,
            'last_name'         => $request->last_name,
            'suffix'            => $request->suffix,
            'birthdate'         => $request->birthdate,
            'gender'            => $request->gender,
            'civil_status'      => $request->civil_status,
            'contact_number'    => $request->contact_number,
            'address'           => $request->address,
            'blood_type'        => $request->blood_type,
            'allergies'         => $request->allergies,
            'emergency_name'    => $request->emergency_name,
            'emergency_contact' => $request->emergency_contact,
            'relationship'      => $request->relationship,
            'emergency_address' => $request->emergency_address,
            'is_walk_in'        => true,
        ]);

        return redirect()->route('staff.patients.index')
            ->with('success', 'Walk-in patient added successfully.');
    }
}