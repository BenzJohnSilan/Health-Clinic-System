<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Appointment;

class DoctorPatientController extends Controller
{
    /**
     * Show all patients who have had appointments with this doctor
     */
    public function index()
    {
        $patients = User::where('role', 'Patient')->get();

        return view('doctor.patient', compact('patients'));
    }

    /**
     * Show specific patient info + their appointment findings (diagnosis)
     */
    public function showRecords($id)
    {
        // ================= PATIENT INFO =================
        $patient = User::where('role', 'Patient')
            ->where('id', $id)
            ->firstOrFail();

        // ================= FINDINGS =================
        // Pull completed appointments that have a diagnosis
        $records = Appointment::where('patient_id', $id)
            ->where('doctor_id', auth()->id())
            ->whereNotNull('diagnosis')
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc')
            ->get();

        return view('doctor.patient-records', compact('patient', 'records'));
    }
}