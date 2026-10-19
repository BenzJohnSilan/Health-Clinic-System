<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratory Request</title>
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', Arial, Helvetica, sans-serif;
            background: #f4f0fa;
            padding: 40px 20px;
            color: #1a1a1a;
        }

        /* ── Action Bar ── */
        .lr-action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 620px;
            margin: 0 auto 16px;
        }

        .btn-lr-print {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 20px;
            background: #6a0dad;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-family: 'DM Sans', Arial, Helvetica, sans-serif;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
        }
        .btn-lr-print:hover { background: #783d7d; }

        .btn-lr-close {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 20px;
            background: #ffffff;
            color: #6a0dad;
            border: 1.5px solid #6a0dad;
            border-radius: 5px;
            font-family: 'DM Sans', Arial, Helvetica, sans-serif;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
        }
        .btn-lr-close:hover { background: #6a0dad; color: #ffffff; }

        /* ── Document Page ── */
        .lr-page {
            width: 100%;
            max-width: 620px;
            margin: 0 auto 60px;
            background: #ffffff;
            border: 1px solid #e3d6f3;
            border-radius: 3px;
            box-shadow: 0 6px 28px rgba(106, 13, 173, 0.12);
            padding: 30px 38px 34px;
            box-sizing: border-box;
        }

        /* ── Clinic Header (same as the Prescription) ── */
        .lr-clinic-header { text-align: center; margin-bottom: 26px; }

        .lr-clinic-name {
            font-family: 'EB Garamond', serif;
            font-size: 28px;
            font-weight: 600;
            color: #1a1a2e;
            letter-spacing: 0.5px;
            line-height: 1.1;
        }

        .lr-clinic-specialty {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #555;
            margin-top: 3px;
        }

        .lr-clinic-address {
            font-size: 11px;
            color: #666;
            margin-top: 4px;
            font-style: italic;
        }

        .lr-clinic-hours {
            display: flex;
            justify-content: center;
            gap: 6px;
            flex-wrap: wrap;
            font-size: 10px;
            color: #888;
            margin-top: 6px;
        }

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
            font-family: 'EB Garamond', serif;
            font-size: 14px;
            font-weight: 600;
            color: #1a1a2e;
            line-height: 1.3;
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
            /* Prints exactly like the on-screen form: same bordered card, same size and spacing.
               "auto" = uses whatever paper is selected in the print dialog (Letter, A4, ...). */
            @page { size: auto; margin: 10mm; }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            html, body { background: #fff !important; padding: 0 !important; margin: 0 !important; }

            .no-print { display: none !important; }

            #lr-printable {
                width: 100% !important;
                max-width: 620px !important;
                margin: 0 auto !important;
                padding: 30px 38px 34px !important;
                border: 1px solid #d0cdc8 !important;
                border-radius: 3px !important;
                box-shadow: none !important;
                break-inside: avoid;
                page-break-inside: avoid;
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
        <button type="button" class="btn-lr-close" id="lrCloseBtn" data-fallback="{{ url()->previous() }}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="2" width="15" height="15">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            Close
        </button>

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

    <script>
        // Opened in its own tab: close it. If the browser won't let the script
        // close the tab, go back to the page the doctor came from instead.
        (function () {
            var btn = document.getElementById('lrCloseBtn');
            btn.addEventListener('click', function () {
                window.close();
                setTimeout(function () {
                    if (!window.closed) { window.location.href = btn.dataset.fallback; }
                }, 250);
            });
        })();
    </script>

</body>
</html>