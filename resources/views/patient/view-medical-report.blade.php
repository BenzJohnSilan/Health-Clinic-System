@extends('layouts.patient')

@section('content')

<link href="https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;500;600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/patient-view-medical-report.css') }}">

<div class="report-page-wrapper">

    {{-- Print button bar --}}
    <div class="print-action-bar">
        <button class="btn-print" onclick="window.print()">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M9 16h6v5H9v-5z"/>
            </svg>
            Print / Save as PDF
        </button>
    </div>

    <div id="medical-report-print" style="width:100%;max-width:780px;">
    <div class="report-card">

        {{-- HEADER --}}
        <div class="rpt-header">
            <div>
                <div class="rpt-clinic-name">Medical Clinic</div>
                <div class="rpt-clinic-sub">Patient Consultation Record</div>
            </div>
            <div class="rpt-doc-tag">
                <div class="rpt-doc-label">Attending Physician</div>
                <div class="rpt-doc-name">
                    Dr. {{ $appointment->doctor->first_name }} {{ $appointment->doctor->last_name }}
                </div>
            </div>
        </div>

        <div class="rpt-title-band">Official Medical Report</div>

        {{-- DATE / TIME --}}
        <div class="rpt-appt-row">
            <div class="rpt-appt-cell">
                <span class="rpt-appt-label">Date</span>
                <span class="rpt-appt-value">
                    {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('M d, Y') }}
                </span>
            </div>
            <div class="rpt-appt-divider"></div>
            <div class="rpt-appt-cell">
                <span class="rpt-appt-label">Time</span>
                <span class="rpt-appt-value">
                    {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}
                </span>
            </div>
            <div class="rpt-appt-divider"></div>
            <div class="rpt-appt-cell">
                <span class="rpt-appt-label">Physician</span>
                <span class="rpt-appt-value">
                    Dr. {{ $appointment->doctor->first_name }} {{ $appointment->doctor->last_name }}
                </span>
            </div>
        </div>

        {{-- PATIENT INFO --}}
        <div class="rpt-section">
            <div class="rpt-section-heading">Patient Information</div>

            <div class="rpt-patient-grid">

                <div class="rpt-patient-cell span-full">
                    <div class="rpt-patient-label">Full Name</div>
                    <div class="rpt-patient-value">
                        {{ $appointment->patient->first_name }}
                        {{ $appointment->patient->middle_name ? $appointment->patient->middle_name . ' ' : '' }}
                        {{ $appointment->patient->last_name }}
                        {{ $appointment->patient->suffix ? ', ' . $appointment->patient->suffix : '' }}
                    </div>
                </div>

                <div class="rpt-patient-cell">
                    <div class="rpt-patient-label">Date of Birth</div>
                    <div class="rpt-patient-value">
                        {{ $appointment->patient->birthdate
                            ? \Carbon\Carbon::parse($appointment->patient->birthdate)->format('M d, Y')
                            : '—' }}
                    </div>
                </div>

                <div class="rpt-patient-cell">
                    <div class="rpt-patient-label">Gender</div>
                    <div class="rpt-patient-value">{{ $appointment->patient->gender ?? '—' }}</div>
                </div>

                <div class="rpt-patient-cell">
                    <div class="rpt-patient-label">Civil Status</div>
                    <div class="rpt-patient-value">{{ $appointment->patient->civil_status ?? '—' }}</div>
                </div>

            </div>
        </div>

        {{-- DIAGNOSIS --}}
        @if($appointment->diagnosis)
        <div class="rpt-section">
            <div class="rpt-section-heading">Diagnosis</div>
            <div class="rpt-diagnosis-box">{{ $appointment->diagnosis }}</div>
        </div>
        @endif

        {{-- PRESCRIPTIONS --}}
        <div class="rpt-section">
            <div class="rpt-section-heading">Prescriptions</div>

            @if($appointment->prescriptions->isNotEmpty())
                <table class="rpt-rx-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Medicine</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Duration</th>
                            <th>Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($appointment->prescriptions as $i => $prescription)
                        <tr>
                            <td>{{ $i + 1 }}</td>
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
            @else
                <p class="rpt-empty">No prescriptions recorded for this appointment.</p>
            @endif
        </div>

        {{-- FOLLOW-UP / REVIEW (FIXED) --}}
        <div class="rpt-section">
            <div class="rpt-section-heading">Follow-up / Review</div>

            @if($appointment->review)
                <div class="rpt-followup-item">
                    <div>
                        <div class="rpt-followup-date-label">Next Review Date</div>
                        <div class="rpt-followup-date-val">
                            {{ $appointment->review->next_review_date
                                ? \Carbon\Carbon::parse($appointment->review->next_review_date)->format('M d, Y')
                                : 'Not set' }}
                        </div>
                    </div>

                    <div>
                        <div class="rpt-followup-msg-label">Remarks / Instructions</div>
                        <div class="rpt-followup-msg">
                            {{ $appointment->review->message ?? 'No message provided.' }}
                        </div>
                    </div>
                </div>
            @else
                <div class="rpt-notes-box">
                    <span class="rpt-notes-empty">No follow-up notes provided.</span>
                </div>
            @endif
        </div>

        {{-- SIGNATURE --}}
        <div class="rpt-signature-row">
            <div class="rpt-note">
                This document is an official medical report generated from the clinic's system.
            </div>

            <div class="rpt-sig-block">
                <div class="rpt-sig-line"></div>
                <div class="rpt-sig-name">
                    Dr. {{ $appointment->doctor->first_name }} {{ $appointment->doctor->last_name }}
                </div>
                <div class="rpt-sig-label">Physician's Signature</div>
            </div>
        </div>

        <div class="rpt-footer">Confidential — For Medical Use Only</div>

    </div>
    </div>

</div>

@endsection