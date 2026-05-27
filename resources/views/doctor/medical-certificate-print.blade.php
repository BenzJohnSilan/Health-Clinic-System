<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Certificate</title>
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
        .mc-action-bar {
            display: flex;
            justify-content: flex-end;
            max-width: 580px;
            margin: 0 auto 16px;
        }

        .btn-mc-print {
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

        .btn-mc-print:hover {
            background: #2d2d4a;
        }

        /* ── Document Page ── */
        .mc-page {
            width: 100%;
            max-width: 580px;
            margin: 0 auto 60px;
            background: #ffffff;
            border: 1px solid #d0cdc8;
            border-radius: 3px;
            box-shadow: 0 6px 28px rgba(0,0,0,0.10);
            padding: 36px 44px 32px;
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
        }

        /* ── Watermark ── */
        .mc-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-family: 'EB Garamond', serif;
            font-size: 80px;
            font-weight: 600;
            color: rgba(26, 26, 46, 0.04);
            letter-spacing: 8px;
            pointer-events: none;
            user-select: none;
            white-space: nowrap;
            z-index: 0;
        }

        /* make sure content stays above watermark */
        .mc-page > *:not(.mc-watermark) {
            position: relative;
            z-index: 1;
        }

        /* ── Clinic Header ── */
        .mc-clinic-header {
            text-align: center;
            margin-bottom: 12px;
        }

        .mc-clinic-name {
            font-family: 'EB Garamond', serif;
            font-size: 30px;
            font-weight: 600;
            color: #1a1a2e;
            letter-spacing: 0.5px;
            line-height: 1.1;
        }

        .mc-clinic-specialty {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #555;
            margin-top: 3px;
        }

        .mc-clinic-address {
            font-size: 11px;
            color: #666;
            margin-top: 4px;
            font-style: italic;
        }

        .mc-clinic-hours {
            display: flex;
            justify-content: center;
            gap: 6px;
            flex-wrap: wrap;
            font-size: 10px;
            color: #888;
            margin-top: 6px;
        }

        /* ── Dividers ── */
        .mc-divider-thick {
            border: none;
            border-top: 2.5px solid #1a1a2e;
            margin: 10px 0;
        }

        .mc-divider-thin {
            border: none;
            border-top: 1px solid #ccc;
            margin: 14px 0;
        }

        /* ── Cert No ── */
        .mc-cert-no {
            text-align: right;
            font-size: 10px;
            color: #888;
            margin-bottom: 4px;
        }

        .mc-cert-no-label {
            font-weight: 600;
            margin-right: 4px;
        }

        /* ── Certificate Title ── */
        .mc-title-section {
            text-align: center;
            padding: 6px 0;
        }

        .mc-title-pre {
            font-size: 10px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #888;
            margin-bottom: 4px;
        }

        .mc-title {
            font-family: 'EB Garamond', serif;
            font-size: 22px;
            font-weight: 600;
            color: #1a1a2e;
            letter-spacing: 0.5px;
        }

        .mc-title-ornament {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 6px;
        }

        .mc-title-ornament span:not(.mc-diamond) {
            display: block;
            width: 60px;
            height: 1px;
            background: #1a1a2e;
        }

        .mc-diamond {
            font-size: 8px;
            color: #1a1a2e;
        }

        /* ── Body Text ── */
        .mc-body-text {
            font-size: 12px;
            color: #444;
            line-height: 1.7;
            margin-bottom: 10px;
        }

        .mc-purpose {
            margin-top: 12px;
            font-style: italic;
        }

        /* ── Patient Block (same field style as prescription) ── */
        .mc-patient-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 4px;
        }

        .mc-patient-row {
            display: flex;
            align-items: baseline;
            gap: 8px;
        }

        .mc-field-label {
            font-size: 11px;
            font-weight: 600;
            color: #444;
            white-space: nowrap;
            flex-shrink: 0;
            min-width: 148px;
        }

        .mc-field-value {
            display: inline-block;
            border-bottom: 1px solid #999;
            flex: 1;
            padding-bottom: 2px;
            font-size: 12px;
            line-height: 1.4;
            color: #1a1a2e;
            min-height: 18px;
        }

        .mc-field-value.mc-name {
            font-family: 'EB Garamond', serif;
            font-size: 16px;
            font-weight: 500;
        }

        /* ── Footer Row ── */
        .mc-footer-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 8px;
            gap: 16px;
        }

        .mc-issued-block {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .mc-issued-label {
            font-size: 11px;
            font-weight: 600;
            color: #444;
        }

        .mc-issued-value {
            display: inline-block;
            border-bottom: 1px solid #999;
            min-width: 150px;
            min-height: 18px;
            font-size: 12px;
            color: #1a1a2e;
            padding-bottom: 2px;
        }

        /* ── Signature Block ── */
        .mc-sig-block {
            text-align: center;
            min-width: 190px;
        }

        .mc-sig-line {
            border-bottom: 1.5px solid #1a1a2e;
            margin-bottom: 6px;
            height: 44px;
        }

        .mc-sig-name {
            font-family: 'EB Garamond', serif;
            font-size: 14px;
            font-weight: 600;
            color: #1a1a2e;
            line-height: 1.3;
        }

        .mc-sig-meta {
            font-size: 10px;
            color: #666;
            margin-top: 2px;
            letter-spacing: 0.3px;
        }

        /* ── Bottom Doc Footer ── */
        .mc-doc-footer {
            margin-top: 14px;
            text-align: center;
            font-size: 9.5px;
            color: #aaa;
            line-height: 1.6;
            font-style: italic;
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

            #mc-printable,
            #mc-printable * { visibility: visible !important; }

            .no-print { display: none !important; }

            #mc-printable {
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

            .mc-clinic-name  { font-size: 24px !important; }
            .mc-title        { font-size: 18px !important; }
            .mc-field-value.mc-name { font-size: 14px !important; }
        }

        /* ══════════════════════
           MOBILE
        ══════════════════════ */
        @media (max-width: 640px) {
            body { padding: 16px; }

            .mc-page { padding: 24px 20px 22px; }

            .mc-patient-row {
                flex-wrap: wrap;
                gap: 4px;
            }

            .mc-field-label { min-width: 100%; }
            .mc-field-value { width: 100%; }

            .mc-footer-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 24px;
            }

            .mc-sig-block { width: 100%; }
        }

    </style>
</head>
<body>

    @php
        $isWalkIn   = $appointment->walkin_patient_id !== null;
        $patientObj = $isWalkIn
            ? $appointment->walkinPatient
            : $appointment->patient;
    @endphp

    {{-- ── Action Bar (hidden on print) ── --}}
    <div class="mc-action-bar no-print">
        <button class="btn-mc-print" onclick="window.print()">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="2" width="15" height="15">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M9 16h6v5H9v-5z"/>
            </svg>
            Print Certificate
        </button>
    </div>

    {{-- ══════════════════════════════════════
         MEDICAL CERTIFICATE (prints this part)
    ══════════════════════════════════════ --}}
    <div class="mc-page" id="mc-printable">

        {{-- Watermark --}}
        <div class="mc-watermark" aria-hidden="true">OFFICIAL</div>

        {{-- ── Clinic Header ── --}}
        <div class="mc-clinic-header">
            <div class="mc-clinic-name">Medical Clinic</div>
            <div class="mc-clinic-specialty">Medical Clinic &amp; Healthcare Services</div>
            <div class="mc-clinic-address">
                Brgy. Dayap Calauan, Laguna
            </div>
            <div class="mc-clinic-hours">
                <span>Mon – Sat &nbsp;9:00 AM – 12:00 PM &nbsp;|&nbsp; 1:00 – 5:00 PM</span>
                <span>Sun – Closed</span>
            </div>
        </div>

        <div class="mc-divider-thick"></div>

        {{-- ── Certificate No ── --}}
        <div class="mc-cert-no">
            <span class="mc-cert-no-label">Certificate No.</span>
            <span>{{ str_pad($appointment->id, 6, '0', STR_PAD_LEFT) }}</span>
        </div>

        {{-- ── Title ── --}}
        <div class="mc-title-section">
            <p class="mc-title-pre">Republic of the Philippines</p>
            <h2 class="mc-title">Medical Certificate</h2>
            <div class="mc-title-ornament">
                <span></span><span class="mc-diamond">◆</span><span></span>
            </div>
        </div>

        <div class="mc-divider-thin"></div>

        {{-- ── Body ── --}}
        <p class="mc-body-text">
            This is to certify that the patient whose information appears below has been examined
            and found to be under medical consultation as described hereunder.
        </p>

        <div class="mc-patient-block">

            <div class="mc-patient-row">
                <span class="mc-field-label">Patient's Full Name:</span>
                <span class="mc-field-value mc-name">
                    {{ strtoupper($patientObj?->last_name ?? '—') }},
                    {{ $patientObj?->first_name ?? '' }}
                    @if($isWalkIn)
                        <small style="font-weight:400; font-size:12px; color:#999; margin-left:6px;">(Walk-in)</small>
                    @endif
                </span>
            </div>

            <div class="mc-patient-row">
                <span class="mc-field-label">Age / Gender:</span>
                <span class="mc-field-value">
                    {{ $patientObj?->age ?? '—' }} &nbsp;/&nbsp; {{ $patientObj?->gender ?? '—' }}
                </span>
            </div>

            <div class="mc-patient-row">
                <span class="mc-field-label">Date of Consultation:</span>
                <span class="mc-field-value">
                    {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}
                </span>
            </div>

            <div class="mc-patient-row">
                <span class="mc-field-label">Attending Physician:</span>
                <span class="mc-field-value">
                    Dr. {{ $appointment->doctor->first_name }} {{ $appointment->doctor->last_name }}
                </span>
            </div>

            @if($medicalRecord?->chief_complaint)
            <div class="mc-patient-row">
                <span class="mc-field-label">Chief Complaint:</span>
                <span class="mc-field-value">{{ $medicalRecord->chief_complaint }}</span>
            </div>
            @endif

            @if($medicalRecord?->diagnosis)
            <div class="mc-patient-row">
                <span class="mc-field-label">Diagnosis / Findings:</span>
                <span class="mc-field-value">{{ $medicalRecord->diagnosis }}</span>
            </div>
            @endif

            @if($medicalRecord?->treatment)
            <div class="mc-patient-row">
                <span class="mc-field-label">Treatment / Remarks:</span>
                <span class="mc-field-value">{{ $medicalRecord->treatment }}</span>
            </div>
            @endif

            @if($medicalRecord?->notes)
            <div class="mc-patient-row">
                <span class="mc-field-label">Additional Notes:</span>
                <span class="mc-field-value">{{ $medicalRecord->notes }}</span>
            </div>
            @endif

        </div>

        <p class="mc-body-text mc-purpose">
            This certificate is issued upon the request of the patient for whatever legal purpose it may serve.
        </p>

        <div class="mc-divider-thin"></div>

        {{-- ── Footer ── --}}
        <div class="mc-footer-row">

            <div class="mc-issued-block">
                <span class="mc-issued-label">Date Issued:</span>
                <span class="mc-issued-value">
                    {{ \Carbon\Carbon::now()->format('F d, Y') }}
                </span>
            </div>

            <div class="mc-sig-block">
                <div class="mc-sig-line"></div>
                <div class="mc-sig-name">
                    Dr. {{ $appointment->doctor->first_name }} {{ $appointment->doctor->last_name }}, MD
                </div>
                <div class="mc-sig-meta">Attending Physician</div>
                <div class="mc-sig-meta">
                    Lic. No. {{ $appointment->doctor->license_number ?? '___________' }}
                </div>
                <div class="mc-sig-meta">
                    PTR No. {{ $appointment->doctor->ptr_number ?? '___________' }}
                </div>
            </div>

        </div>{{-- /.mc-footer-row --}}

        {{-- ── Bottom Note ── --}}
        <div class="mc-doc-footer">
            <p>This document is valid only when signed and stamped with the official clinic seal.</p>
            <p>For verification, contact the clinic at (02) 8123-4567.</p>
        </div>

    </div>{{-- /#mc-printable --}}

</body>
</html>