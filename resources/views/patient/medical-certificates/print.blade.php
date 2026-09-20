<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Certificate — {{ $certificate->certificate_number }}</title>
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            background: #ffffff;
            color: #000000;
        }

        body {
            font-family: 'DM Sans', Arial, sans-serif;
            padding: 28px 16px 60px;
        }

        /* -- Action Bar (screen only) -- */
        .mc-action-bar {
            display: flex;
            justify-content: center;
            gap: 10px;
            max-width: 720px;
            margin: 0 auto 18px;
        }

        .btn-mc-print {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 22px;
            background: #000000;
            color: #ffffff;
            border: 1px solid #000000;
            border-radius: 3px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-mc-print:hover {
            background: #222222;
        }

        /* -- Document Page -- */
        .mc-page {
            width: 100%;
            max-width: 720px;
            min-height: 960px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #000000;
            padding: 34px 42px 26px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }

        /* -- Clinic Header -- */
        .mc-clinic-header {
            display: flex;
            align-items: center;
            gap: 14px;
            justify-content: center;
            text-align: center;
        }

        .mc-clinic-logo img {
            max-height: 62px;
            max-width: 62px;
            object-fit: contain;
            filter: grayscale(100%);
        }

        .mc-clinic-name-block { text-align: center; }

        .mc-clinic-name {
            font-family: 'EB Garamond', Georgia, serif;
            font-size: 25px;
            font-weight: 700;
            letter-spacing: 0.3px;
            line-height: 1.15;
            text-transform: uppercase;
        }

        .mc-clinic-specialty {
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: 1.6px;
            text-transform: uppercase;
            color: #333333;
            margin-top: 3px;
        }

        .mc-clinic-address {
            font-size: 10.5px;
            color: #333333;
            margin-top: 3px;
        }

        .mc-clinic-contact {
            font-size: 10.5px;
            color: #333333;
            margin-top: 1px;
        }

        .mc-divider-thick { border: none; border-top: 2px solid #000000; margin: 10px 0 6px; }
        .mc-divider-thin  { border: none; border-top: 1px solid #000000; margin: 10px 0; }

        .mc-cert-no { text-align: right; font-size: 10.5px; margin-top: 2px; }
        .mc-cert-no-label { font-weight: 700; margin-right: 4px; }

        .mc-title-section { text-align: center; padding: 4px 0 2px; }
        .mc-title {
            font-family: 'EB Garamond', Georgia, serif;
            font-size: 21px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .mc-body-text { font-size: 11.5px; line-height: 1.6; margin-bottom: 8px; text-align: justify; }
        .mc-section-label {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin: 12px 0 6px;
            border-bottom: 1px solid #000000;
            padding-bottom: 2px;
        }

        .mc-field-block { display: flex; flex-direction: column; gap: 6px; }
        .mc-field-row { display: flex; align-items: baseline; gap: 8px; }

        .mc-field-label {
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
            flex-shrink: 0;
            min-width: 160px;
        }

        .mc-field-value {
            display: inline-block;
            border-bottom: 1px solid #000000;
            flex: 1;
            padding-bottom: 2px;
            font-size: 11.5px;
            line-height: 1.45;
            min-height: 16px;
            word-break: break-word;
        }

        .mc-field-value.mc-name {
            font-weight: 700;
            text-transform: uppercase;
        }

        .mc-two-col { display: flex; gap: 24px; }
        .mc-two-col .mc-field-row { flex: 1; }
        .mc-two-col .mc-field-label { min-width: 90px; }

        .mc-spacer { flex: 1; }

        .mc-footer-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 20px;
            gap: 20px;
        }

        .mc-issued-block { display: flex; flex-direction: column; gap: 4px; }
        .mc-issued-label { font-size: 10.5px; font-weight: 700; }
        .mc-issued-value {
            display: inline-block;
            border-bottom: 1px solid #000000;
            min-width: 150px;
            min-height: 16px;
            font-size: 11.5px;
            padding-bottom: 2px;
        }

        .mc-seal-block { text-align: center; min-width: 110px; }
        .mc-seal-box {
            width: 100px;
            height: 78px;
            border: 1px dashed #000000;
            margin: 0 auto 5px;
        }
        .mc-seal-caption { font-size: 8.5px; letter-spacing: 0.5px; text-transform: uppercase; color: #333333; }

        .mc-sig-block { text-align: center; min-width: 210px; }
        .mc-sig-line {
            border-bottom: 1.5px solid #000000;
            margin-bottom: 5px;
            height: 42px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }
        .mc-sig-line img { max-height: 40px; max-width: 100%; object-fit: contain; filter: grayscale(100%) contrast(1.1); }

        .mc-sig-name {
            font-size: 12.5px;
            font-weight: 700;
        }
        .mc-sig-meta { font-size: 10px; margin-top: 1px; }

        .mc-doc-footer {
            margin-top: 16px;
            text-align: center;
            font-size: 9px;
            color: #333333;
            line-height: 1.5;
        }

        /* -- Print rules -- */
        @media print {
            @page { size: A4 portrait; margin: 12mm 14mm; }

            html, body {
                background: #ffffff !important;
            }

            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

            body * { visibility: hidden !important; }
            #mc-printable, #mc-printable * { visibility: visible !important; }
            .no-print { display: none !important; }

            body { padding: 0 !important; margin: 0 !important; }

            #mc-printable {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                min-height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
            }
        }

        @media (max-width: 640px) {
            body { padding: 14px 10px 40px; }
            .mc-page { padding: 22px 18px 18px; min-height: 0; }
            .mc-field-row { flex-wrap: wrap; gap: 3px; }
            .mc-field-label { min-width: 100%; }
            .mc-field-value { width: 100%; }
            .mc-two-col { flex-direction: column; gap: 6px; }
            .mc-footer-row { flex-direction: column; align-items: flex-start; gap: 20px; }
            .mc-sig-block, .mc-seal-block { width: 100%; }
        }

    </style>
</head>
<body>

    @php
        $patient = $certificate->resolvedPatient();
        $doctor  = $certificate->doctor;
        $medicalRecord = $certificate->appointment?->medicalRecord;
    @endphp

    {{-- Action Bar (hidden on print) --}}
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

    {{-- MEDICAL CERTIFICATE (prints this part) --}}
    <div class="mc-page" id="mc-printable">

        {{-- 1. CLINIC HEADER --}}
        <div class="mc-clinic-header">
            <div class="mc-clinic-name-block">
                <div class="mc-clinic-name">Health Medical Clinic</div>
                <div class="mc-clinic-specialty">General Practice &amp; Healthcare Services</div>
                <div class="mc-clinic-address">Brgy. Dayap, Calauan, Laguna</div>
                <div class="mc-clinic-contact">Mon&ndash;Sat 9:00 AM&ndash;12:00 PM &amp; 1:00&ndash;5:00 PM &nbsp;|&nbsp; Tel. (02) 8123-4567</div>
            </div>
        </div>

        <div class="mc-divider-thick"></div>

        <div class="mc-cert-no">
            <span class="mc-cert-no-label">Certificate No.</span>
            <span>{{ $certificate->certificate_number }}</span>
        </div>

        {{-- 2. DOCUMENT TITLE --}}
        <div class="mc-title-section">
            <h2 class="mc-title">Medical Certificate</h2>
        </div>

        <div class="mc-divider-thin"></div>

        {{-- 3. CERTIFICATION STATEMENT --}}
        <p class="mc-body-text">
            This is to certify that the above-named patient was seen and examined at this clinic
            on the date indicated below.
        </p>

        {{-- 4. PATIENT INFORMATION --}}
        <div class="mc-section-label">Patient Information</div>
        <div class="mc-field-block">

            <div class="mc-field-row">
                <span class="mc-field-label">Patient's Full Name:</span>
                <span class="mc-field-value mc-name">
                    {{ strtoupper($patient?->last_name ?? '-') }}{{ $patient?->first_name ? ', ' . $patient->first_name : '' }}
                </span>
            </div>

            <div class="mc-two-col">
                <div class="mc-field-row">
                    <span class="mc-field-label">Age:</span>
                    <span class="mc-field-value">{{ $patient?->age ?? '-' }}</span>
                </div>
                <div class="mc-field-row">
                    <span class="mc-field-label">Sex/Gender:</span>
                    <span class="mc-field-value">{{ $patient?->gender ?? '-' }}</span>
                </div>
            </div>

            <div class="mc-field-row">
                <span class="mc-field-label">Date Examined:</span>
                <span class="mc-field-value">
                    {{ optional($certificate->appointment)->appointment_date
                        ? \Carbon\Carbon::parse($certificate->appointment->appointment_date)->format('F d, Y')
                        : '-' }}
                </span>
            </div>

        </div>

        {{-- 5. MEDICAL FINDINGS --}}
        <div class="mc-section-label">Medical Findings</div>
        <div class="mc-field-block">

            @if($medicalRecord?->chief_complaint)
            <div class="mc-field-row">
                <span class="mc-field-label">Chief Complaint:</span>
                <span class="mc-field-value">{{ $medicalRecord->chief_complaint }}</span>
            </div>
            @endif

            <div class="mc-field-row">
                <span class="mc-field-label">Diagnosis / Findings:</span>
                <span class="mc-field-value">{{ $certificate->medical_statement }}</span>
            </div>

        </div>

        {{-- 6. RECOMMENDATION --}}
        <div class="mc-section-label">Recommendation</div>
        <div class="mc-field-block">

            <div class="mc-field-row">
                <span class="mc-field-label">Recommendation:</span>
                <span class="mc-field-value">{{ $certificate->recommendation }}</span>
            </div>

            @if($certificate->rest_start_date && $certificate->rest_end_date)
            <div class="mc-field-row">
                <span class="mc-field-label">Advised Rest Period:</span>
                <span class="mc-field-value">
                    {{ $certificate->rest_start_date->format('F d, Y') }}
                    to
                    {{ $certificate->rest_end_date->format('F d, Y') }}
                    (Return on {{ $certificate->rest_end_date->copy()->addDay()->format('F d, Y') }})
                </span>
            </div>
            @endif

            @if($certificate->additional_remarks)
            <div class="mc-field-row">
                <span class="mc-field-label">Additional Remarks:</span>
                <span class="mc-field-value">{{ $certificate->additional_remarks }}</span>
            </div>
            @endif

        </div>

        {{-- 7 & 8. PURPOSE + ISSUANCE STATEMENT --}}
        <p class="mc-body-text" style="margin-top: 10px;">
            This certificate is issued upon the request of the patient for the purpose of:
            <strong>{{ $certificate->displayPurpose() }}</strong>.
        </p>

        <div class="mc-spacer"></div>

        <div class="mc-divider-thin"></div>

        <div class="mc-footer-row">

            {{-- 9. DATE ISSUED --}}
            <div class="mc-issued-block">
                <span class="mc-issued-label">Date Issued:</span>
                <span class="mc-issued-value">
                    {{ $certificate->issued_at?->format('F d, Y') ?? '-' }}
                </span>
            </div>

            {{-- 11. CLINIC SEAL --}}
            <div class="mc-seal-block">
                <div class="mc-seal-box"></div>
                <div class="mc-seal-caption">Clinic Seal</div>
            </div>

            {{-- 10. DOCTOR INFORMATION --}}
            <div class="mc-sig-block">
                <div class="mc-sig-line">
                    @if($doctor?->signature)
                        <img src="{{ asset('storage/'.$doctor->signature) }}" alt="Signature">
                    @endif
                </div>
                <div class="mc-sig-name">
                    Dr. {{ $doctor?->first_name }} {{ $doctor?->last_name }}, MD
                </div>
                <div class="mc-sig-meta">Attending Physician</div>
                <div class="mc-sig-meta">
                    PRC Lic. No. {{ $doctor?->license_number ?? '___________' }}
                </div>
                <div class="mc-sig-meta">
                    PTR No. ___________
                </div>
            </div>

        </div>

        <div class="mc-doc-footer">
            <p>This document is valid only when signed and stamped with the official clinic seal.</p>
        </div>

    </div>

</body>
</html>
