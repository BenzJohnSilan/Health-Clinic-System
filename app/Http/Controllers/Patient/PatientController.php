<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Appointment;
use App\Models\Prescription;
use App\Models\MedicalRecord;
use App\Models\Review;
use App\Models\UserLog;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class PatientController extends Controller
{
    public function dashboard()
    {
        $patient = auth()->user();

        $totalAppointments = Appointment::where('patient_id', $patient->id)->count();

        $completedAppointments = Appointment::where('patient_id', $patient->id)
            ->where('status', 'Completed')
            ->count();

        $cancelledAppointments = Appointment::where('patient_id', $patient->id)
            ->whereIn('status', ['Cancelled', 'Rejected'])
            ->count();

        $upcomingAppointments = Appointment::with('doctor')
            ->where('patient_id', $patient->id)
            ->where('status', 'Approved')
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->get();

        $upcomingCount = $upcomingAppointments->count();

        $thisMonthCount = Appointment::where('patient_id', $patient->id)
            ->whereYear('appointment_date', Carbon::now()->year)
            ->whereMonth('appointment_date', Carbon::now()->month)
            ->count();

        $doctors = User::where('role', 'Doctor')->get();

        $bookedSlots = Appointment::where('status', '!=', 'Rejected')
            ->get(['doctor_id', 'appointment_date', 'appointment_time']);

        return view('patient.dashboard', compact(
            'patient',
            'totalAppointments',
            'completedAppointments',
            'cancelledAppointments',
            'upcomingAppointments',
            'upcomingCount',
            'thisMonthCount',
            'doctors',
            'bookedSlots'
        ));
    }

    public function profile()
    {
        $patient = auth()->user();
        return view('patient.profile', compact('patient'));
    }

    // ✅ ACCOUNT SETTINGS PAGE
    public function settings()
    {
        $patient = auth()->user();
        return view('patient.account-settings', compact('patient'));
    }

    // =========================
    // PROFILE UPDATE (ACCOUNT SETTINGS)
    // =========================
    public function updateProfile(Request $request)
    {
        $patient = auth()->user();

        $validated = $request->validate([
            'first_name'               => 'required|string|max:255',
            'middle_name'              => 'nullable|string|max:255',
            'last_name'                => 'required|string|max:255',
            'suffix'                   => 'nullable|string|max:50',
            'gender'                   => 'required|string|in:Male,Female,Other',
            'civil_status'             => 'required|string|in:Single,Married,Widowed,Separated',
            'address'                  => 'required|string|max:500',
            'contact_number'           => 'required|string|max:20',
            'username'                 => ['required', 'string', 'max:255', Rule::unique('users')->ignore($patient->id)],
            'email'                    => ['required', 'email', Rule::unique('users')->ignore($patient->id)],
            'avatar'                   => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

            // Medical Information
            'blood_type'               => 'nullable|string|max:10',
            'allergies'                => 'nullable|string|max:1000',

            // Emergency Contact
            'emergency_name'           => 'nullable|string|max:100',
            'relationship'             => 'nullable|string|max:50',
            'emergency_contact_number' => 'nullable|string|max:20',
            'emergency_address'        => 'nullable|string|max:100',
        ]);

        $patient->update([
            'first_name'               => $validated['first_name'],
            'middle_name'              => $validated['middle_name'] ?? null,
            'last_name'                => $validated['last_name'],
            'suffix'                   => $validated['suffix'] ?? null,
            'gender'                   => $validated['gender'],
            'civil_status'             => $validated['civil_status'],
            'address'                  => $validated['address'],
            'contact_number'           => $validated['contact_number'],
            'username'                 => $validated['username'],
            'email'                    => $validated['email'],

            // Medical Information
            'blood_type'               => $validated['blood_type'] ?? null,
            'allergies'                => $validated['allergies'] ?? null,

            // Emergency Contact
            'emergency_name'           => $validated['emergency_name'] ?? null,
            'relationship'             => $validated['relationship'] ?? null,
            'emergency_contact_number' => $validated['emergency_contact_number'] ?? null,
            'emergency_address'        => $validated['emergency_address'] ?? null,
        ]);

        // Avatar upload
        if ($request->hasFile('avatar')) {
            if ($patient->avatar && Storage::disk('public')->exists($patient->avatar)) {
                Storage::disk('public')->delete($patient->avatar);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $patient->avatar = $path;
            $patient->save();
        }

        $this->logActivity('Updated Profile', 'Updated profile information.');

        return redirect()->route('patient.settings')
            ->with('success', 'Profile updated successfully!');
    }

    // =========================
    // PASSWORD UPDATE (INSIDE SETTINGS)
    // =========================
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|confirmed|min:6',
        ]);

        $patient = auth()->user();

        if (!Hash::check($request->current_password, $patient->password)) {
            return back()->withErrors([
                'current_password' => 'Current password does not match.'
            ]);
        }

        $patient->password = Hash::make($request->password);
        $patient->save();

        $this->logActivity('Changed Password', 'Account password was changed.');

        return redirect()->route('patient.settings')
            ->with('success', 'Password updated successfully.');
    }

    // =========================
    // MEDICAL REPORTS
    // =========================
    public function medicalReport()
    {
        $patient = auth()->user();

        $appointments = Appointment::with([
                'doctor',
                'prescriptions.medicine',
                'review'
            ])
            ->where('patient_id', $patient->id)
            ->whereIn('status', ['Completed', 'Cancelled', 'Rejected', 'No Show'])
            ->orderBy('appointment_date', 'desc')
            ->paginate(10);

        return view('patient.appointment-history', compact('appointments'));
    }

    /**
     * Medical Report (SINGLE VIEW)
     */
    public function showMedicalReport($id)
    {
        $appointment = Appointment::with([
                'doctor',
                'patient',
                'prescriptions.medicine',
                'review'
            ])
            ->where('id', $id)
            ->where('patient_id', auth()->id())
            ->firstOrFail();

        $prescriptions = $appointment->prescriptions;
        $review        = $appointment->review;
        $medicalRecord = MedicalRecord::where('appointment_id', $id)->first();

        // ACTIVITY LOG
        $this->logActivity(
            'Viewed Medical Report',
            'Viewed medical report from Dr. ' . $appointment->doctor->first_name . ' ' . $appointment->doctor->last_name .
            ' on ' . Carbon::parse($appointment->appointment_date)->format('F d, Y')
        );

        return view('patient.view-medical-report', compact(
            'appointment',
            'prescriptions',
            'review',
            'medicalRecord'
        ));
    }

    public function showPrescription($id)
    {
        $appointment = Appointment::with(['doctor', 'patient', 'review'])
            ->where('id', $id)
            ->where('patient_id', auth()->id())
            ->firstOrFail();

        $prescriptions = $appointment->prescriptions;
        $review        = $appointment->review;

        // ACTIVITY LOG
        $this->logActivity(
            'Viewed Prescription',
            'Viewed prescription from Dr. ' . $appointment->doctor->first_name . ' ' . $appointment->doctor->last_name .
            ' on ' . Carbon::parse($appointment->appointment_date)->format('F d, Y')
        );

        return view('patient.prescription', compact(
            'appointment',
            'prescriptions',
            'review'
        ));
    }

    public function showMedicalCertificate($id)
    {
        $appointment = Appointment::with([
                'doctor',
                'patient',
                'prescriptions.medicine',
                'review'
            ])
            ->where('id', $id)
            ->where('patient_id', auth()->id())
            ->firstOrFail();

        $medicalRecord = MedicalRecord::where('appointment_id', $id)->first();
        $prescriptions = $appointment->prescriptions;
        $review        = $appointment->review;

        // ACTIVITY LOG
        $this->logActivity(
            'Viewed Medical Certificate',
            'Viewed medical certificate from Dr. ' . $appointment->doctor->first_name . ' ' . $appointment->doctor->last_name .
            ' on ' . Carbon::parse($appointment->appointment_date)->format('F d, Y')
        );

        return view('patient.medical-certificate', compact(
            'appointment',
            'medicalRecord',
            'prescriptions',
            'review'
        ));
    }

    public function activityLogs(Request $request)
    {
        $user = auth()->user();

        $query = UserLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        // Search by keyword (details)
        if ($request->filled('search')) {
            $query->where('details', 'like', '%' . $request->search . '%');
        }

        // Filter by action
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Filter by month
        if ($request->filled('month')) {
            $query->whereMonth('created_at', $request->month);
        }

        // Filter by year
        if ($request->filled('year')) {
            $query->whereYear('created_at', $request->year);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(10)->withQueryString();

        // Distinct actions para sa dropdown
        $actions = UserLog::where('user_id', $user->id)
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        // Available years para sa dropdown
        $years = UserLog::where('user_id', $user->id)
            ->selectRaw('YEAR(created_at) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        return view('patient.activity-logs', compact('logs', 'actions', 'years'));
    }

    // =========================
    // ACTIVITY LOGGER
    // =========================
    private function logActivity($action, $details = null)
    {
        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => $action,
            'details' => $details,
        ]);
    }
}