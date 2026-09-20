@extends('layouts.patient')

@section('head')
<link href="https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;500;600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/patient-view-medical-report.css') }}">
@endsection

@section('content')

<div class="mr-wrapper">

    {{-- ================= BACK ROW (own row, above header) ================= --}}
    <div class="mr-back-row">
        <a href="{{ route('patient.appointment.history') }}" class="mr-btn-back">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Appointment History
        </a>
    </div>

    {{-- ================= PAGE HEADER ================= --}}
    <div class="mr-page-header">
        <div class="mr-page-header-text">
            <h1>Medical Report</h1>
            <p>Consultation details and medical information</p>
        </div>

        <a href="{{ route('patient.prescription.show', ['id' => $appointment->id, 'autoprint' => 1]) }}"
           class="mr-btn-print">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M9 16h6v5H9v-5z"/>
            </svg>
            Print Prescription
        </a>
    </div>

    {{-- ================= CONSULTATION INFORMATION ================= --}}
    <div class="mr-card">
        <div class="mr-card-heading">Consultation Information</div>
        <div class="mr-grid mr-grid-3">
            <div class="mr-field">
                <span class="mr-label">Date</span>
                <span class="mr-value">
                    {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('M d, Y') }}
                </span>
            </div>
            <div class="mr-field">
                <span class="mr-label">Time</span>
                <span class="mr-value">
                    {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}
                </span>
            </div>
            <div class="mr-field">
                <span class="mr-label">Attending Physician</span>
                <span class="mr-value">
                    Dr. {{ $appointment->doctor->first_name ?? '' }} {{ $appointment->doctor->last_name ?? '' }}
                </span>
            </div>
        </div>
    </div>

    {{-- ================= PATIENT INFORMATION ================= --}}
    <div class="mr-card">
        <div class="mr-card-heading">Patient Information</div>
        <div class="mr-grid mr-grid-3">
            <div class="mr-field mr-field-full">
                <span class="mr-label">Full Name</span>
                <span class="mr-value">
                    {{ $appointment->patient->first_name }}
                    {{ $appointment->patient->middle_name ? $appointment->patient->middle_name . ' ' : '' }}
                    {{ $appointment->patient->last_name }}
                    {{ $appointment->patient->suffix ? ', ' . $appointment->patient->suffix : '' }}
                </span>
            </div>
            <div class="mr-field">
                <span class="mr-label">Date of Birth</span>
                <span class="mr-value">
                    {{ $appointment->patient->birthdate
                        ? \Carbon\Carbon::parse($appointment->patient->birthdate)->format('M d, Y')
                        : '—' }}
                </span>
            </div>
            <div class="mr-field">
                <span class="mr-label">Gender</span>
                <span class="mr-value">{{ $appointment->patient->gender ?? '—' }}</span>
            </div>
            <div class="mr-field">
                <span class="mr-label">Civil Status</span>
                <span class="mr-value">{{ $appointment->patient->civil_status ?? '—' }}</span>
            </div>
        </div>
    </div>

    {{-- ================= CHIEF COMPLAINT ================= --}}
    <div class="mr-card">
        <div class="mr-card-heading">Chief Complaint</div>
        @if($medicalRecord && $medicalRecord->chief_complaint)
            <div class="mr-text-box">{{ $medicalRecord->chief_complaint }}</div>
        @else
            <div class="mr-empty">No chief complaint recorded.</div>
        @endif
    </div>

    {{-- ================= VITAL SIGNS ================= --}}
    <div class="mr-card">
        <div class="mr-card-heading">Vital Signs</div>
        <div class="mr-grid mr-grid-4">
            <div class="mr-vital">
                <span class="mr-label">Blood Pressure</span>
                <span class="mr-value">{{ $medicalRecord?->blood_pressure ?? '—' }}</span>
            </div>
            <div class="mr-vital">
                <span class="mr-label">Temperature</span>
                <span class="mr-value">{{ $medicalRecord?->temperature ? $medicalRecord->temperature . ' °C' : '—' }}</span>
            </div>
            <div class="mr-vital">
                <span class="mr-label">Weight</span>
                <span class="mr-value">{{ $medicalRecord?->weight ? $medicalRecord->weight . ' kg' : '—' }}</span>
            </div>
            <div class="mr-vital">
                <span class="mr-label">Height</span>
                <span class="mr-value">{{ $medicalRecord?->height ? $medicalRecord->height . ' cm' : '—' }}</span>
            </div>
        </div>
    </div>

    {{-- ================= DIAGNOSIS & TREATMENT ================= --}}
    <div class="mr-two-col">
        <div class="mr-card">
            <div class="mr-card-heading">Diagnosis</div>
            @if($medicalRecord && $medicalRecord->diagnosis)
                <div class="mr-text-box">{{ $medicalRecord->diagnosis }}</div>
            @else
                <div class="mr-empty">No diagnosis recorded.</div>
            @endif
        </div>
        <div class="mr-card">
            <div class="mr-card-heading">Treatment</div>
            @if($medicalRecord && $medicalRecord->treatment)
                <div class="mr-text-box">{{ $medicalRecord->treatment }}</div>
            @else
                <div class="mr-empty">No treatment recorded.</div>
            @endif
        </div>
    </div>

    {{-- ================= NOTES / REMARKS ================= --}}
    <div class="mr-card">
        <div class="mr-card-heading">Notes / Remarks</div>
        @if($medicalRecord && $medicalRecord->notes)
            <div class="mr-text-box">{{ $medicalRecord->notes }}</div>
        @elseif($review && $review->message)
            <div class="mr-text-box">{{ $review->message }}</div>
        @else
            <div class="mr-empty">No notes or remarks provided.</div>
        @endif
    </div>

    {{-- ================= PRESCRIPTION (VIEW ONLY) ================= --}}
    <div class="mr-card">
        <div class="mr-card-heading">Prescription</div>

        @if($prescriptions->isNotEmpty())
            <div class="mr-table-wrapper">
                <table class="mr-rx-table">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Duration</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($prescriptions as $prescription)
                        <tr>
                            <td>
                                <strong>
                                    {{ $prescription->medicine?->medicine_name
                                        ?? $prescription->manual_medicine_name
                                        ?? 'N/A' }}
                                </strong>
                            </td>
                            <td>{{ $prescription->dosage ?? '—' }}</td>
                            <td>{{ $prescription->frequency ?? '—' }}</td>
                            <td>{{ $prescription->duration ?? '—' }}</td>
                            <td>{{ $prescription->quantity_prescribed ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="mr-empty">No prescriptions recorded for this appointment.</div>
        @endif
    </div>

</div>

@endsection
