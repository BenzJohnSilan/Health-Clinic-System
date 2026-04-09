<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\User; // Para sa registered patients
use Carbon\Carbon;

class AdminPatientController extends Controller
{
    // ===============================
    // SHOW ALL PATIENTS
    // ===============================
    public function index()
    {
        // Kunin lahat ng registered patients mula sa users table
        $registered = User::where('role', 'Patient')
                          ->get()
                          ->map(function($user) {
                              return (object)[
                                  'id' => $user->id,
                                  'first_name' => $user->first_name,
                                  'middle_name' => $user->middle_name,
                                  'last_name' => $user->last_name,
                                  'suffix' => $user->suffix,
                                  'birthdate' => $user->birthdate,
                                  'age' => Carbon::parse($user->birthdate)->age,
                                  'gender' => $user->gender,
                                  'civil_status' => $user->civil_status,
                                  'contact_number' => $user->contact_number,
                                  'address' => $user->address,
                                  'is_walk_in' => false, // registered
                              ];
                          });

        // Kunin lahat ng walk-in patients
        $walkins = Patient::all();

        // Merge both collections at sort by newest first
        $patients = $registered->merge($walkins)->sortByDesc('id');

        return view('admin.patients.index', compact('patients'));
    }

    // ===============================
    // STORE NEW PATIENT (WALK-IN)
    // ===============================
    public function store(Request $request)
    {
        // Validation
        $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:50',
            'birthdate' => 'required|date|before:today',
            'age' => 'nullable|integer',
            'gender' => 'required|in:Male,Female,Other',
            'civil_status' => 'required|in:Single,Married,Widowed,Separated',
            'contact_number' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
        ]);

        // Auto-compute age kung wala o mali ang age sa form
        $age = $request->age ?? Carbon::parse($request->birthdate)->age;

        // Create Walk-in Patient
        Patient::create([
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'suffix' => $request->suffix,
            'birthdate' => $request->birthdate,
            'age' => $age,
            'gender' => $request->gender,
            'civil_status' => $request->civil_status,
            'contact_number' => $request->contact_number,
            'address' => $request->address,
            'is_walk_in' => true,
        ]);

        // Redirect pabalik sa list at ipakita success message
        return redirect()
            ->route('admin.patients.index')
            ->with('success', 'Patient added successfully!');
    }
}