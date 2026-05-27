<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Prescription</title>
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: #f0ede8;
            padding: 40px 20px;
            color: #1a1a1a;
        }

        /* ── Action Bar ── */
        .rx-action-bar {
            display: flex;
            justify-content: flex-end;
            max-width: 580px;
            margin: 0 auto 16px;
        }

        .btn-rx-print {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 20px;
            background: #1a1a2e;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-rx-print:hover {
            background: #2d2d4a;
        }

        /* ── Prescription Page ── */
        .rx-page {
            width: 100%;
            max-width: 580px;
            margin: 0 auto 60px;
            background: #ffffff;
            border: 1px solid #d0cdc8;
            border-radius: 3px;
            box-shadow: 0 6px 28px rgba(0,0,0,0.10);
            padding: 36px 44px 32px;
            box-sizing: border-box;
        }

        /* ── Clinic Header ── */
        .rx-clinic-header {
            text-align: center;
            margin-bottom: 12px;
        }

        .rx-clinic-name {
            font-family: 'EB Garamond', serif;
            font-size: 30px;
            font-weight: 600;
            color: #1a1a2e;
            letter-spacing: 0.5px;
            line-height: 1.1;
        }

        .rx-clinic-specialty {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #555;
            margin-top: 3px;
        }

        .rx-clinic-address {
            font-size: 11px;
            color: #666;
            margin-top: 4px;
            font-style: italic;
        }

        .rx-clinic-hours {
            display: flex;
            justify-content: center;
            gap: 6px;
            flex-wrap: wrap;
            font-size: 10px;
            color: #888;
            margin-top: 6px;
        }

        /* ── Dividers ── */
        .rx-divider-thick {
            border: none;
            border-top: 2.5px solid #1a1a2e;
            margin: 10px 0;
        }

        .rx-divider-thin {
            border: none;
            border-top: 1px solid #ccc;
            margin: 14px 0;
        }

        /* ── Patient Info Rows ── */
        .rx-patient-row {
            display: flex;
            align-items: baseline;
            gap: 14px;
            margin-bottom: 7px;
        }

        .rx-patient-row--second {
            gap: 10px;
        }

        .rx-patient-field {
            display: flex;
            align-items: baseline;
            gap: 5px;
            flex: 1;
            min-width: 0;
        }

        .rx-patient-field.rx-field-short { flex: 0 0 150px; }
        .rx-patient-field.rx-field-tiny  { flex: 0 0 72px; }

        .rx-field-label {
            font-size: 11px;
            font-weight: 600;
            color: #444;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .rx-field-value,
        .rx-field-underline {
            display: inline-block;
            border-bottom: 1px solid #999;
            width: 100%;
            padding-bottom: 2px;
            font-size: 12px;
            line-height: 1.4;
            color: #1a1a2e;
            min-height: 18px;
        }

        /* ── Rx Body ── */
        .rx-body {
            display: flex;
            gap: 18px;
            align-items: flex-start;
            padding: 12px 0 6px;
            min-height: 200px;
        }

        .rx-symbol {
            font-family: 'EB Garamond', serif;
            font-size: 54px;
            font-weight: 700;
            color: #1a1a2e;
            line-height: 1;
            flex-shrink: 0;
            margin-top: -4px;
            letter-spacing: -2px;
        }

        .rx-drugs {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 18px;
            padding-top: 6px;
        }

        .rx-drug-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .rx-drug-name {
            font-family: 'EB Garamond', serif;
            font-size: 18px;
            font-weight: 500;
            color: #1a1a2e;
            line-height: 1.2;
        }

        .rx-drug-dosage {
            font-size: 15px;
            font-weight: 400;
            color: #444;
            margin-left: 4px;
        }

        .rx-drug-qty {
            font-size: 14px;
            color: #444;
        }

        .rx-drug-sig {
            font-size: 12px;
            color: #555;
            padding-left: 10px;
            line-height: 1.6;
        }

        .rx-sig-label {
            font-style: italic;
            font-weight: 600;
            color: #333;
            margin-right: 3px;
        }

        .rx-no-drugs {
            font-size: 12px;
            color: #bbb;
            font-style: italic;
        }

        /* ── Footer ── */
        .rx-footer-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 8px;
            gap: 16px;
        }

        .rx-followup-block {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .rx-followup-date {
            display: inline-block;
            border-bottom: 1px solid #999;
            min-width: 150px;
            min-height: 18px;
            font-size: 12px;
            color: #1a1a2e;
            padding-bottom: 2px;
        }

        .rx-sig-block {
            text-align: center;
            min-width: 190px;
        }

        .rx-sig-line {
            border-bottom: 1.5px solid #1a1a2e;
            margin-bottom: 6px;
            height: 44px;
        }

        .rx-sig-name {
            font-family: 'EB Garamond', serif;
            font-size: 14px;
            font-weight: 600;
            color: #1a1a2e;
            line-height: 1.3;
        }

        .rx-sig-meta {
            font-size: 10px;
            color: #666;
            margin-top: 2px;
            letter-spacing: 0.3px;
        }

        /* ══════════════════════
           PRINT STYLES
        ══════════════════════ */
        @media print {
            @page {
                size: A5 portrait;
                margin: 10mm 12mm;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body * { visibility: hidden !important; }

            #rx-printable,
            #rx-printable * { visibility: visible !important; }

            .no-print { display: none !important; }

            #rx-printable {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
            }

            .rx-clinic-name { font-size: 24px !important; }
            .rx-symbol      { font-size: 44px !important; }
            .rx-drug-name   { font-size: 15px !important; }

            .rx-drug-item { page-break-inside: avoid; }
        }

        /* ══════════════════════
           MOBILE
        ══════════════════════ */
        @media (max-width: 640px) {
            body { padding: 16px; }

            .rx-page { padding: 24px 20px 22px; }

            .rx-patient-row {
                flex-wrap: wrap;
                gap: 8px;
            }

            .rx-patient-field.rx-field-short,
            .rx-patient-field.rx-field-tiny {
                flex: 0 0 100%;
            }

            .rx-footer-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 24px;
            }

            .rx-sig-block { width: 100%; }
        }
    </style>
</head>
<body>

    @php
        $isWalkIn = $appointment->walkin_patient_id !== null;
        $patient  = $isWalkIn
            ? $appointment->walkinPatient
            : $appointment->patient;
    @endphp

    {{-- ── Action Bar (hidden on print) ── --}}
    <div class="rx-action-bar no-print">
        <button class="btn-rx-print" onclick="window.print()">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="2" width="15" height="15">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M9 16h6v5H9v-5z"/>
            </svg>
            Print / Save as PDF
        </button>
    </div>

    {{-- ══════════════════════════════════════
         PRESCRIPTION SLIP (prints this part)
    ══════════════════════════════════════ --}}
    <div class="rx-page" id="rx-printable">

        {{-- ── Clinic Header ── --}}
        <div class="rx-clinic-header">
            <div class="rx-clinic-name">Medical Clinic</div>
            <div class="rx-clinic-specialty">Medical Clinic &amp; Healthcare Services</div>
            <div class="rx-clinic-address">
                Brgy. Dayap Calauan, Laguna
            </div>
            <div class="rx-clinic-hours">
                <span>Mon – Sat &nbsp;9:00 AM – 12:00 PM &nbsp;|&nbsp; 1:00 – 5:00 PM</span>
                <span>Sun – Closed</span>
            </div>
        </div>

        <div class="rx-divider-thick"></div>

        {{-- ── Patient Info ── --}}
        <div class="rx-patient-row">
            <div class="rx-patient-field">
                <span class="rx-field-label">Patient Name:</span>
                <span class="rx-field-underline">
                    {{ $patient?->first_name ?? '' }}
                    {{ isset($patient->middle_name) && $patient->middle_name ? $patient->middle_name . ' ' : '' }}
                    {{ $patient?->last_name ?? '' }}
                    {{ isset($patient->suffix) && $patient->suffix ? ', ' . $patient->suffix : '' }}
                </span>
            </div>
            <div class="rx-patient-field rx-field-short">
                <span class="rx-field-label">Date:</span>
                <span class="rx-field-underline">
                    {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('m/d/Y') }}
                </span>
            </div>
        </div>

        <div class="rx-patient-row rx-patient-row--second">
            <div class="rx-patient-field">
                <span class="rx-field-label">Address:</span>
                <span class="rx-field-underline">
                    {{ $patient?->address ?? '' }}
                </span>
            </div>
            <div class="rx-patient-field rx-field-tiny">
                <span class="rx-field-label">Age:</span>
                <span class="rx-field-underline">
                    @if(isset($patient->birthdate) && $patient->birthdate)
                        {{ \Carbon\Carbon::parse($patient->birthdate)->age }}
                    @elseif(isset($patient->age))
                        {{ $patient->age }}
                    @endif
                </span>
            </div>
            <div class="rx-patient-field rx-field-tiny">
                <span class="rx-field-label">Sex:</span>
                <span class="rx-field-underline">
                    {{ $patient?->gender ?? '' }}
                </span>
            </div>
        </div>

        @if(isset($appointment->reference_no) && $appointment->reference_no)
        <div class="rx-patient-row">
            <div class="rx-patient-field" style="flex:1;">
                <span class="rx-field-label">Reference No.:</span>

                <span class="rx-field-underline">
                    {{ $appointment->reference_no }}
                </span>
            </div>
        </div>
        @endif

        <div class="rx-divider-thin"></div>

        {{-- ── Rx Body ── --}}
        <div class="rx-body">

            <div class="rx-symbol">&#8478;</div>

            <div class="rx-drugs">
                @forelse($prescriptions as $prescription)
                    <div class="rx-drug-item">
                        <div class="rx-drug-name">
                            {{ $prescription->medicine?->medicine_name
                                ?? $prescription->manual_medicine_name
                                ?? 'N/A' }}
                            @if($prescription->dosage)
                                <span class="rx-drug-dosage">{{ $prescription->dosage }}</span>
                            @endif
                            @if($prescription->quantity_prescribed)
                                <span class="rx-drug-qty">&nbsp;#{{ $prescription->quantity_prescribed }}</span>
                            @endif
                        </div>
                        <div class="rx-drug-sig">
                            <span class="rx-sig-label">Sig:</span>
                            {{ $prescription->frequency ?? '' }}
                            @if($prescription->duration)
                                &nbsp;{{ $prescription->duration }}
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rx-no-drugs">No prescriptions recorded.</div>
                @endforelse
            </div>

        </div>{{-- /.rx-body --}}

        <div class="rx-divider-thin"></div>

        {{-- ── Footer ── --}}
        <div class="rx-footer-row">

            <div class="rx-followup-block">
                <span class="rx-field-label">Follow-up on:</span>
                <span class="rx-followup-date">
                    @if(isset($review) && $review && $review->next_review_date)
                        {{ \Carbon\Carbon::parse($review->next_review_date)->format('m/d/Y') }}
                    @endif
                </span>
            </div>

            <div class="rx-sig-block">
                <div class="rx-sig-line"></div>
                <div class="rx-sig-name">
                    Dr. {{ $appointment->doctor->first_name }} {{ $appointment->doctor->last_name }}, MD
                </div>
                <div class="rx-sig-meta">
                    Lic. No. {{ $appointment->doctor->license_number ?? '___________' }}
                </div>
                <div class="rx-sig-meta">
                    PTR No. {{ $appointment->doctor->ptr_number ?? '___________' }}
                </div>
            </div>

        </div>{{-- /.rx-footer-row --}}

    </div>{{-- /#rx-printable --}}

</body>
</html>