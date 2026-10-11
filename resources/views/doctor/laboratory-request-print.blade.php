<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratory Request</title>
    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #e9e9e9;
            padding: 40px 20px;
            color: #000;
        }

        /* ── Action Bar ── */
        .lr-action-bar {
            display: flex;
            justify-content: flex-end;
            max-width: 620px;
            margin: 0 auto 16px;
        }

        .btn-lr-print {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 20px;
            background: #000;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
        }
        .btn-lr-print:hover { background: #333; }

        /* ── Document Page ── */
        .lr-page {
            width: 100%;
            max-width: 620px;
            margin: 0 auto 60px;
            background: #ffffff;
            border: 1px solid #000;
            padding: 30px 38px 34px;
            box-sizing: border-box;
        }

        /* ── Clinic Header (kept as-is) ── */
        .lr-clinic-header { text-align: center; margin-bottom: 14px; }

        .lr-clinic-name {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.5px;
            line-height: 1.1;
        }

        .lr-clinic-specialty {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #333;
            margin-top: 3px;
        }

        .lr-clinic-address {
            font-size: 11px;
            color: #333;
            margin-top: 4px;
        }

        .lr-clinic-hours {
            display: flex;
            justify-content: center;
            gap: 6px;
            flex-wrap: wrap;
            font-size: 10px;
            color: #444;
            margin-top: 6px;
        }

        .lr-divider-thick { border: none; border-top: 2px solid #000; margin: 10px 0 18px; }

        /* ── Patient Info ── */
        .lr-patient-line {
            display: flex;
            align-items: baseline;
            gap: 6px;
            margin-bottom: 10px;
            font-size: 13px;
        }

        .lr-patient-line__label { font-weight: 600; white-space: nowrap; }

        .lr-patient-line__fill {
            flex: 1;
            border-bottom: 1px solid #000;
            min-height: 15px;
            padding-bottom: 2px;
        }

        .lr-patient-row3 {
            display: flex;
            gap: 22px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .lr-patient-row3 .lr-patient-line { margin-bottom: 0; flex: 1; }

        /* ── Title ── */
        .lr-title {
            text-align: center;
            font-size: 19px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 22px;
            text-transform: uppercase;
        }

        /* ── Tests Checklist (two columns) ── */
        .lr-tests-columns {
            display: flex;
            gap: 34px;
            margin-bottom: 4px;
        }

        .lr-tests-col { flex: 1; }

        .lr-test-row {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 13px;
            padding: 3px 0;
        }

        .lr-checkbox {
            width: 13px;
            height: 13px;
            border: 1.3px solid #000;
            flex-shrink: 0;
            margin-top: 2px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            line-height: 1;
            font-weight: 700;
        }

        /* ── Free-text tests (X-Ray / Ultrasound / Others) ── */
        .lr-freetext-section { margin-top: 12px; }

        .lr-freetext-row {
            display: flex;
            align-items: baseline;
            gap: 8px;
            font-size: 13px;
            padding: 5px 0;
        }

        .lr-freetext-row .lr-label { font-weight: 600; white-space: nowrap; }

        .lr-freetext-row .lr-fill {
            flex: 1;
            border-bottom: 1px solid #000;
            min-height: 15px;
            padding-bottom: 2px;
        }

        /* ── Diagnosis ── */
        .lr-diagnosis { margin-top: 22px; }

        .lr-diagnosis__label {
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .lr-diagnosis__line {
            border-bottom: 1px solid #000;
            height: 20px;
        }

        /* ── Signature Block ── */
        .lr-footer {
            display: flex;
            justify-content: flex-end;
            margin-top: 40px;
        }

        .lr-sig-block {
            text-align: center;
            min-width: 230px;
        }

        .lr-sig-line {
            border-bottom: 1px solid #000;
            min-height: 30px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }

        .lr-sig-line img { max-height: 34px; max-width: 100%; object-fit: contain; }

        .lr-sig-caption {
            font-size: 12.5px;
            margin-top: 3px;
        }

        .lr-sig-meta {
            text-align: left;
            font-size: 12px;
            margin-top: 10px;
        }
        .lr-sig-meta div { margin-top: 5px; border-bottom: 1px solid #000; padding-bottom: 2px; }
        .lr-sig-meta span.lr-meta-label { font-weight: 600; }

        /* ══════════════════════
           PRINT STYLES
        ══════════════════════ */
        @media print {
            @page { size: letter portrait; margin: 12mm 14mm; }

            body { background: #fff; padding: 0; }

            body * { visibility: hidden !important; }
            #lr-printable, #lr-printable * { visibility: visible !important; }
            .no-print { display: none !important; }

            #lr-printable {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
            }
        }

        /* ══════════════════════
           MOBILE
        ══════════════════════ */
        @media (max-width: 640px) {
            body { padding: 16px; }
            .lr-page { padding: 22px 18px 24px; }
            .lr-tests-columns { flex-direction: column; gap: 0; }
            .lr-patient-row3 { flex-direction: column; gap: 10px; }
        }

    </style>
</head>
<body>

    @php
        $isWalkIn   = $laboratoryRequest->walkin_patient_id !== null;
        $patientObj = $isWalkIn ? $laboratoryRequest->walkinPatient : $laboratoryRequest->patient;

        // Which of the requested tests are checked, keyed by name for
        // quick lookup while rendering each checkbox below.
        $checkedTests = $laboratoryRequest->tests->pluck('test_name')->flip();

        // Two-column layout matching the clinic's paper Laboratory
        // Request form. Names must match LaboratoryRequest::TEST_CATALOG
        // exactly so the checkbox lookup above resolves correctly.
        $leftColumn = [
            'CBC', 'FBS', 'HbA1c', 'Lipid Profile', 'ALT / SGPT',
            'AST / SGOT', 'Blood Uric Acid', 'BUN', 'Creatinine',
            'Cholesterol', 'Sodium', 'Potassium',
        ];
        $rightColumn = [
            'Peripheral Blood Smear', 'Alkaline Phosphatase', 'TSH', 'T3', 'T4',
            'HBsAg', 'Urinalysis', '12-L ECG',
        ];
        $testLabels = [
            'HBsAg' => 'HBsAg (Screening/Titer)',
        ];
    @endphp

    {{-- ── Action Bar (hidden on print) ── --}}
    <div class="lr-action-bar no-print">
        <button class="btn-lr-print" onclick="window.print()">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="2" width="15" height="15">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M9 16h6v5H9v-5z"/>
            </svg>
            Print Laboratory Request
        </button>
    </div>

    {{-- ══════════════════════════════════════
         LABORATORY REQUEST (prints this part)
    ══════════════════════════════════════ --}}
    <div class="lr-page" id="lr-printable">

        {{-- ── Clinic Header ── --}}
        <div class="lr-clinic-header">
            <div class="lr-clinic-name">Medical Clinic</div>
            <div class="lr-clinic-specialty">Medical Clinic &amp; Healthcare Services</div>
            <div class="lr-clinic-address">
                Brgy. Dayap Calauan, Laguna
            </div>
            <div class="lr-clinic-hours">
                <span>Mon – Sat &nbsp;9:00 AM – 12:00 PM &nbsp;|&nbsp; 1:00 – 5:00 PM</span>
                <span>Sun – Closed</span>
            </div>
        </div>

        <div class="lr-divider-thick"></div>

        {{-- ── Patient Info ── --}}
        <div class="lr-patient-line">
            <span class="lr-patient-line__label">Patient's Name:</span>
            <span class="lr-patient-line__fill">
                {{ strtoupper($patientObj?->last_name ?? '') }}{{ $patientObj ? ',' : '' }}
                {{ $patientObj?->first_name ?? '' }}
            </span>
        </div>

        <div class="lr-patient-row3">
            <div class="lr-patient-line">
                <span class="lr-patient-line__label">Age:</span>
                <span class="lr-patient-line__fill">{{ $patientObj?->age ?? '' }}</span>
            </div>
            <div class="lr-patient-line">
                <span class="lr-patient-line__label">Gender:</span>
                <span class="lr-patient-line__fill">{{ $patientObj?->gender ?? '' }}</span>
            </div>
            <div class="lr-patient-line">
                <span class="lr-patient-line__label">Date:</span>
                <span class="lr-patient-line__fill">
                    {{ $laboratoryRequest->requested_at?->format('F d, Y') ?? \Carbon\Carbon::parse($laboratoryRequest->created_at)->format('F d, Y') }}
                </span>
            </div>
        </div>

        {{-- ── Title ── --}}
        <div class="lr-title">Laboratory Request Form</div>

        {{-- ── Requested Tests (two columns) ── --}}
        <div class="lr-tests-columns">
            <div class="lr-tests-col">
                @foreach($leftColumn as $test)
                    <div class="lr-test-row">
                        <span class="lr-checkbox">{{ isset($checkedTests[$test]) ? '✓' : '' }}</span>
                        <span>{{ $testLabels[$test] ?? strtoupper($test) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="lr-tests-col">
                @foreach($rightColumn as $test)
                    <div class="lr-test-row">
                        <span class="lr-checkbox">{{ isset($checkedTests[$test]) ? '✓' : '' }}</span>
                        <span>{{ $testLabels[$test] ?? strtoupper($test) }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ── X-Ray / Ultrasound / Others (free text) ── --}}
        <div class="lr-freetext-section">
            <div class="lr-freetext-row">
                <span class="lr-checkbox">{{ isset($checkedTests['X-Ray']) ? '✓' : '' }}</span>
                <span class="lr-label">XRAY:</span>
                <span class="lr-fill"></span>
            </div>
            <div class="lr-freetext-row">
                <span class="lr-checkbox">{{ isset($checkedTests['Ultrasound']) ? '✓' : '' }}</span>
                <span class="lr-label">ULTRASOUND:</span>
                <span class="lr-fill"></span>
            </div>
            <div class="lr-freetext-row">
                <span class="lr-checkbox">{{ isset($checkedTests['Others']) ? '✓' : '' }}</span>
                <span class="lr-label">OTHERS:</span>
                <span class="lr-fill">{{ $laboratoryRequest->other_test_description }}</span>
            </div>
        </div>

        {{-- ── Diagnosis ── --}}
        <div class="lr-diagnosis">
            <div class="lr-diagnosis__label">Diagnosis:</div>
            <div class="lr-diagnosis__line">{{ $laboratoryRequest->diagnosis }}</div>
            <div class="lr-diagnosis__line"></div>
        </div>

        {{-- ── Signature ── --}}
        <div class="lr-footer">
            <div class="lr-sig-block">
                <div class="lr-sig-line">
                    @if($laboratoryRequest->doctor->signature)
                        <img src="{{ asset('storage/' . $laboratoryRequest->doctor->signature) }}" alt="Signature">
                    @endif
                </div>
                <div class="lr-sig-caption">
                    Dr. {{ $laboratoryRequest->doctor->first_name }} {{ $laboratoryRequest->doctor->last_name }}, MD
                </div>

                <div class="lr-sig-meta">
                    <div><span class="lr-meta-label">Lic. No.</span> {{ $laboratoryRequest->doctor->license_number ?? '' }}</div>
                    <div><span class="lr-meta-label">PTR. No.</span></div>
                    <div><span class="lr-meta-label">S2 No.</span></div>
                </div>
            </div>
        </div>

    </div>{{-- /#lr-printable --}}

</body>
</html>