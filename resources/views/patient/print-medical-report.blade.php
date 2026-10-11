<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medical Report - {{ $appointment->reference_no ?? 'Consultation' }}</title>

{{-- Same font families as the Prescription print --}}
<link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/patient-print-medical-report.css') }}">
</head>
<body>

@php
    // Patient supports both registered and walk-in records, same as the Prescription print.
    $patient = $appointment->patient ?? $appointment->walkinPatient;
    $doctor  = $appointment->doctor;

    $fullName = trim(
        ($patient->first_name ?? '') . ' ' .
        (!empty($patient->middle_name) ? $patient->middle_name . ' ' : '') .
        ($patient->last_name ?? '')
    ) . (!empty($patient->suffix) ? ', ' . $patient->suffix : '');

    // Vital signs: only fields that exist on medical_records AND have a value.
    $vitals = [];
    if ($medicalRecord) {
        if (filled($medicalRecord->blood_pressure)) $vitals['Blood Pressure'] = $medicalRecord->blood_pressure;
        if (filled($medicalRecord->temperature))    $vitals['Temperature']    = $medicalRecord->temperature . ' °C';
        if (filled($medicalRecord->weight))         $vitals['Weight']         = $medicalRecord->weight . ' kg';
        if (filled($medicalRecord->height))         $vitals['Height']         = $medicalRecord->height . ' cm';
    }

    // Clinical notes: existing MedicalRecord field only. History of Present
    // Illness and Physical Examination are intentionally NOT shown to the
    // Patient (they stay in the Doctor/Staff clinical record) — same rule
    // as the Consultation Summary page.
    $findings = [];
    if ($medicalRecord) {
        if (filled($medicalRecord->notes)) $findings['Notes'] = $medicalRecord->notes;
    }

    $hasFollowUp = $review && ($review->next_review_date || filled($review->message));
@endphp

{{-- Screen-only controls (hidden when printing) --}}
<div class="pr-action-bar no-print">
    <a href="{{ route('patient.appointment.show', $appointment->id) }}" class="pr-btn pr-btn--outline">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="15" height="15">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
        Back
    </a>

    <button type="button" class="pr-btn pr-btn--solid" onclick="window.print()">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="15" height="15">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M9 16h6v5H9v-5z"/>
        </svg>
        Print / Save as PDF
    </button>
</div>

<main class="pr-sheet" id="medical-report-printable">

    {{-- ================= CLINIC HEADER (same content/structure as the Prescription) ================= --}}
    <header class="pr-clinic-header">
        <div class="pr-clinic-name">Medical Clinic</div>
        <div class="pr-clinic-specialty">General Practice</div>
        <div class="pr-clinic-address">Brgy. Dayap Calauan, Laguna</div>
        <div class="pr-clinic-hours">
            <span>Mon – Sat &nbsp;9:00 AM – 12:00 PM &nbsp;|&nbsp; 1:00 – 5:00 PM</span>
            <span>Sun – Closed</span>
        </div>
    </header>

    <div class="pr-divider-thick"></div>

    <h1 class="pr-title">Medical Report</h1>

    {{-- ================= PATIENT INFORMATION ================= --}}
    <section class="pr-section pr-keep">
        <h2 class="pr-heading">Patient Information</h2>
        <div class="pr-row">
            <div class="pr-field">
                <span class="pr-label">Patient Name:</span>
                <span class="pr-value">{{ $fullName }}</span>
            </div>
        </div>
        <div class="pr-row">
            <div class="pr-field">
                <span class="pr-label">Date of Birth:</span>
                <span class="pr-value">
                    {{ !empty($patient->birthdate) ? \Carbon\Carbon::parse($patient->birthdate)->format('M d, Y') : '' }}
                </span>
            </div>
            <div class="pr-field pr-field--sm">
                <span class="pr-label">Age:</span>
                <span class="pr-value">
                    {{ !empty($patient->birthdate) ? \Carbon\Carbon::parse($patient->birthdate)->age : '' }}
                </span>
            </div>
            <div class="pr-field pr-field--sm">
                <span class="pr-label">Gender:</span>
                <span class="pr-value">{{ $patient->gender ?? '' }}</span>
            </div>
        </div>
        <div class="pr-row">
            <div class="pr-field">
                <span class="pr-label">Address:</span>
                <span class="pr-value">{{ $patient->address ?? '' }}</span>
            </div>
        </div>
    </section>

    {{-- ================= CONSULTATION INFORMATION ================= --}}
    <section class="pr-section pr-keep">
        <h2 class="pr-heading">Consultation Information</h2>
        <div class="pr-row">
            <div class="pr-field">
                <span class="pr-label">Date:</span>
                <span class="pr-value">{{ \Carbon\Carbon::parse($appointment->appointment_date)->format('M d, Y') }}</span>
            </div>
            <div class="pr-field">
                <span class="pr-label">Time:</span>
                <span class="pr-value">{{ $appointment->timeLabel('h:i A') }}</span>
            </div>
        </div>
        <div class="pr-row">
            <div class="pr-field">
                <span class="pr-label">Attending Physician:</span>
                <span class="pr-value">Dr. {{ $doctor->first_name ?? '' }} {{ $doctor->last_name ?? '' }}</span>
            </div>
            @if($appointment->reference_no)
            <div class="pr-field">
                <span class="pr-label">Reference No.:</span>
                <span class="pr-value">{{ $appointment->reference_no }}</span>
            </div>
            @endif
        </div>
    </section>

    {{-- ================= CHIEF COMPLAINT ================= --}}
    @if($medicalRecord && filled($medicalRecord->chief_complaint))
    <section class="pr-section">
        <h2 class="pr-heading">Chief Complaint / Reason for Consultation</h2>
        <div class="pr-text">{{ $medicalRecord->chief_complaint }}</div>
    </section>
    @endif

    {{-- ================= VITAL SIGNS ================= --}}
    @if(count($vitals))
    <section class="pr-section pr-keep">
        <h2 class="pr-heading">Vital Signs</h2>
        <table class="pr-table pr-table--vitals">
            <thead>
                <tr>
                    @foreach($vitals as $label => $value)
                        <th>{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    @foreach($vitals as $label => $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </section>
    @endif

    {{-- ================= CLINICAL FINDINGS / NOTES ================= --}}
    @if(count($findings))
    <section class="pr-section">
        <h2 class="pr-heading">Clinical Findings / Notes</h2>
        @foreach($findings as $label => $text)
            <div class="pr-subblock">
                <div class="pr-sublabel">{{ $label }}</div>
                <div class="pr-text">{{ $text }}</div>
            </div>
        @endforeach
    </section>
    @endif

    {{-- ================= DIAGNOSIS ================= --}}
    @if($medicalRecord && filled($medicalRecord->diagnosis))
    <section class="pr-section">
        <h2 class="pr-heading">Diagnosis</h2>
        <div class="pr-text">{{ $medicalRecord->diagnosis }}</div>
    </section>
    @endif

    {{-- ================= TREATMENT / MEDICAL ADVICE ================= --}}
    @if($medicalRecord && filled($medicalRecord->treatment))
    <section class="pr-section">
        <h2 class="pr-heading">Treatment / Medical Advice</h2>
        <div class="pr-text">{{ $medicalRecord->treatment }}</div>
    </section>
    @endif

    {{-- ================= PRESCRIPTION ================= --}}
    @if($prescriptions->isNotEmpty())
    <section class="pr-section">
        <h2 class="pr-heading">Prescription</h2>
        <table class="pr-table pr-table--rx">
            <thead>
                <tr>
                    <th class="col-medicine">Medicine</th>
                    <th>Dosage</th>
                    <th>Frequency</th>
                    <th>Duration</th>
                    <th class="col-qty">Qty</th>
                </tr>
            </thead>
            <tbody>
                @foreach($prescriptions as $prescription)
                <tr>
                    <td class="col-medicine">
                        <strong>
                            {{ $prescription->medicine?->medicine_name
                                ?? $prescription->manual_medicine_name
                                ?? 'N/A' }}
                        </strong>
                    </td>
                    <td>{{ $prescription->dosage ?? '—' }}</td>
                    <td>{{ $prescription->frequency ?? '—' }}</td>
                    <td>{{ $prescription->duration ?? '—' }}</td>
                    <td class="col-qty">{{ $prescription->quantity_prescribed ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </section>
    @endif

    {{-- ================= LABORATORY RESULTS (completed / doctor-reviewed only) ================= --}}
    @if($laboratoryRequests->isNotEmpty())
    <section class="pr-section">
        <h2 class="pr-heading">Laboratory Results</h2>

        @foreach($laboratoryRequests as $labRequest)
            @php $currentByTest = $labRequest->currentResultsByTest(); @endphp
            <div class="pr-lab">
                <div class="pr-lab-meta">
                    <span><strong>Request:</strong> {{ $labRequest->reference_no }}</span>
                    @if($labRequest->requested_at)
                        <span><strong>Requested:</strong> {{ $labRequest->requested_at->format('M d, Y') }}</span>
                    @endif
                </div>

                <table class="pr-table pr-table--lab">
                    <thead>
                        <tr>
                            <th class="col-test">Test</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($currentByTest as $testName => $file)
                        <tr>
                            <td class="col-test">
                                <strong>
                                    {{ $testName === 'Others' && $labRequest->other_test_description
                                        ? 'Others (' . $labRequest->other_test_description . ')'
                                        : $testName }}
                                </strong>
                            </td>
                            <td>
                                @if($file)
                                    Result on file &mdash; {{ $file->original_filename }}
                                @else
                                    Not uploaded
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                @if(filled($labRequest->review_notes))
                    <div class="pr-subblock">
                        <div class="pr-sublabel">Physician's Review</div>
                        <div class="pr-text">{{ $labRequest->review_notes }}</div>
                    </div>
                @endif

                @if($labRequest->follow_up_recommended && filled($labRequest->follow_up_instructions))
                    <div class="pr-subblock">
                        <div class="pr-sublabel">Follow-up Instructions</div>
                        <div class="pr-text">{{ $labRequest->follow_up_instructions }}</div>
                    </div>
                @endif

                @if($labRequest->reviewed_at)
                    <div class="pr-lab-reviewed">
                        Reviewed {{ $labRequest->reviewed_at->format('M d, Y') }}
                        @if($labRequest->reviewedBy)
                            by Dr. {{ $labRequest->reviewedBy->first_name }} {{ $labRequest->reviewedBy->last_name }}
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </section>
    @endif

    {{-- ================= FOLLOW-UP ================= --}}
    @if($hasFollowUp)
    <section class="pr-section pr-keep">
        <h2 class="pr-heading">Follow-up</h2>
        @if($review->next_review_date)
            <div class="pr-row">
                <div class="pr-field pr-field--half">
                    <span class="pr-label">Follow-up on:</span>
                    <span class="pr-value">{{ \Carbon\Carbon::parse($review->next_review_date)->format('M d, Y') }}</span>
                </div>
            </div>
        @endif
        @if(filled($review->message))
            <div class="pr-text pr-text--spaced">{{ $review->message }}</div>
        @endif
    </section>
    @endif

    {{-- ================= ATTENDING PHYSICIAN / SIGNATURE ================= --}}
    <footer class="pr-physician pr-keep">
        <div class="pr-sig-block">
            <div class="pr-sig-line">
                @if($doctor?->signature)
                    <img src="{{ asset('storage/'.$doctor->signature) }}" alt="Doctor's Signature"
                         onerror="this.style.display='none'">
                @endif
            </div>
            <div class="pr-sig-name">Dr. {{ $doctor->first_name ?? '' }} {{ $doctor->last_name ?? '' }}, MD</div>
            <div class="pr-sig-role">Attending Physician</div>
            <div class="pr-sig-meta">License No. {{ $doctor->license_number ?? '___________' }}</div>
            <div class="pr-sig-meta">PTR No. {{ $doctor->ptr_number ?? '___________' }}</div>
            <div class="pr-sig-meta">Date: {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('M d, Y') }}</div>
        </div>
    </footer>

</main>

@if($autoprint ?? false)
<script>
    // Reached from the Consultation Summary's "Print Summary" button —
    // open the browser print dialog once fonts have loaded.
    window.addEventListener('load', function () {
        var ready = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
        ready.then(function () { setTimeout(function () { window.print(); }, 150); });
    });
</script>
@endif

</body>
</html>
