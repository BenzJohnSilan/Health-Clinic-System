<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;

class AdminPatientController extends Controller
{
    // ===============================
    // SHOW ALL PATIENTS
    // ===============================
    public function index()
    {
        // ==============================
        // REGISTERED PATIENTS (USERS TABLE)
        // ==============================
        $registered = User::where('role', 'Patient')
            ->get()
            ->map(function ($user) {
                return [
                    'id'             => $user->id,
                    'first_name'     => $user->first_name,
                    'middle_name'    => $user->middle_name,
                    'last_name'      => $user->last_name,
                    'suffix'         => $user->suffix,
                    'birthdate'      => $user->birthdate,
                    // ✅ computed dito para consistent sa view
                    'age'            => $user->birthdate
                        ? Carbon::parse($user->birthdate)->age
                        : null,
                    'gender'         => $user->gender,
                    'civil_status'   => $user->civil_status,
                    'contact_number' => $user->contact_number,
                    'address'        => $user->address,
                    'is_walk_in'     => false,
                ];
            });

        // ==============================
        // WALK-IN PATIENTS (PATIENTS TABLE)
        // ==============================
        $walkins = Patient::all()
            ->map(function ($patient) {
                return [
                    'id'             => $patient->id,
                    'first_name'     => $patient->first_name,
                    'middle_name'    => $patient->middle_name,
                    'last_name'      => $patient->last_name,
                    'suffix'         => $patient->suffix,
                    'birthdate'      => $patient->birthdate,
                    // ✅ $patient->age — via model accessor, auto-computed
                    'age'            => $patient->age,
                    'gender'         => $patient->gender,
                    'civil_status'   => $patient->civil_status,
                    'contact_number' => $patient->contact_number,
                    'address'        => $patient->address,
                    'is_walk_in'     => true,
                ];
            });

        // ==============================
        // MERGE SAFELY AS COLLECTION
        // ==============================
        $patients = collect()
            ->merge($registered)
            ->merge($walkins)
            ->sortByDesc('id')
            ->values();

        return view('admin.patients.index', compact('patients'));
    }

    // ===============================
    // STORE NEW WALK-IN PATIENT
    // ===============================
    public function store(Request $request)
    {
        $request->validate([
            'first_name'     => 'required|string|max:255',
            'middle_name'    => 'nullable|string|max:255',
            'last_name'      => 'required|string|max:255',
            'suffix'         => 'nullable|string|max:50',
            'birthdate'      => 'required|date|before:today',
            // ❌ 'age' validation — INALIS, hindi na kailangan
            'gender'         => 'required|in:Male,Female,Other',
            'civil_status'   => 'required|in:Single,Married,Widowed,Separated',
            'contact_number' => 'nullable|string|max:20',
            'address'        => 'nullable|string|max:255',
        ]);

        // ✅ NO age field — ang Patient model accessor na bahala sa computation
        Patient::create([
            'first_name'     => $request->first_name,
            'middle_name'    => $request->middle_name,
            'last_name'      => $request->last_name,
            'suffix'         => $request->suffix,
            'birthdate'      => $request->birthdate,
            // ❌ 'age' => ... — INALIS
            'gender'         => $request->gender,
            'civil_status'   => $request->civil_status,
            'contact_number' => $request->contact_number,
            'address'        => $request->address,
            'is_walk_in'     => true,
        ]);

        return redirect()
            ->route('admin.patients.index')
            ->with('success', 'Patient added successfully!');
    }
}