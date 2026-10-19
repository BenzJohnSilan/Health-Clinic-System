<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Certificate — {{ $certificate->certificate_number }}</title>
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        @page { size: A4; margin: 12mm 16mm; }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body { background: #f4f0fa; color: #000; }

        body {
            font-family: 'Times New Roman', Times, 'Liberation Serif', serif;
            padding: 24px 16px 60px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ---------- screen-only action bar (same look as the Prescription print) ---------- */
        .mc-action-bar {
            display: flex; justify-content: space-between; align-items: center;
            max-width: 794px; margin: 4px auto 14px; padding: 0 2px;
            font-family: 'DM Sans', Arial, sans-serif;
        }
        .btn-mc-back, .btn-mc-print {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 20px; border-radius: 5px;
            font-family: 'DM Sans', Arial, sans-serif; font-size: 13px; font-weight: 500;
            cursor: pointer; transition: background .2s, color .2s;
        }
        .btn-mc-back { background: #fff; color: #6a0dad; border: 1.5px solid #6a0dad; }
        .btn-mc-back:hover { background: #6a0dad; color: #fff; }
        .btn-mc-print { background: #6a0dad; color: #fff; border: none; }
        .btn-mc-print:hover { background: #783d7d; }
        .mc-sign-note { max-width: 794px; margin: 0 auto 14px; text-align: center; font: 13px Arial, sans-serif; color: #6b7280; }

        /* ---------- the sheet ---------- */
        .mc-page {
            background: #fff;
            width: 100%;
            max-width: 794px;
            min-height: 1000px;
            margin: 0 auto;
            padding: 40px 56px 36px;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .18);
        }

        /* ---------- header (same as the Prescription print) ---------- */
        .mc-clinic-header { text-align: center; }
        .mc-clinic-name {
            font-family: 'EB Garamond', serif; font-size: 28px; font-weight: 600;
            color: #1a1a2e; letter-spacing: 0.5px; line-height: 1.1;
        }
        .mc-clinic-specialty {
            font-family: 'DM Sans', Arial, sans-serif;
            font-size: 10px; font-weight: 600; letter-spacing: 2.5px; text-transform: uppercase;
            color: #555; margin-top: 3px;
        }
        .mc-clinic-address { font-family: 'DM Sans', Arial, sans-serif; font-size: 11px; color: #666; margin-top: 4px; font-style: italic; }
        .mc-clinic-hours {
            font-family: 'DM Sans', Arial, sans-serif;
            display: flex; justify-content: center; flex-wrap: wrap;
            font-size: 10px; color: #888; margin-top: 6px;
        }
        .mc-clinic-hours span + span { margin-left: 8px; }

        .mc-title {
            text-align: center; font-size: 24px; font-weight: 700; font-style: italic;
            text-transform: uppercase; margin-top: 34px;
        }

        /* ---------- date (right aligned, underlined) ---------- */
        .mc-date { width: 240px; margin: 20px 0 0 auto; text-align: center; font-size: 17px; line-height: 1.3; }
        .mc-date .value { border-bottom: 1.5px solid #000; padding: 0 6px 2px; }

        /* ---------- body ---------- */
        .mc-concern { font-weight: 700; font-size: 17px; margin-top: 26px; }

        .mc-text { font-size: 17px; line-height: 2.1; text-align: justify; margin-top: 8px; }
        .fill { border-bottom: 1.5px solid #000; padding: 0 6px; }
        .sel  { text-decoration: underline; text-underline-offset: 3px; text-decoration-thickness: 1.5px; }

        /* Final Diagnosis + Remarks: every line is the same thin 1.5px rule,
           and the typed text sits right on its line. */
        .mc-row { display: flex; align-items: flex-end; font-size: 17px; line-height: 1.2; margin-top: 18px; }
        .mc-row .lbl  { font-weight: 700; white-space: nowrap; margin-right: 10px; }
        .mc-row .line { flex: 1; border-bottom: 1.5px solid #000; padding: 0 6px 1px; min-height: 1.2em; }

        .mc-remarks-lbl { font-weight: 700; font-size: 17px; line-height: 1.2; margin-top: 18px; }
        .mc-remark-line {
            display: flex; align-items: flex-end; min-height: 32px;
            border-bottom: 1.5px solid #000; padding: 0 6px 1px;
            font-size: 17px; line-height: 1.2;
        }

        .mc-issued { font-size: 17px; line-height: 2.1; text-align: justify; margin-top: 24px; }

        /* ---------- signature (left blank on purpose: signed by hand) ---------- */
        .mc-sign { width: 280px; margin: 34px 0 0 auto; text-align: center; break-inside: avoid; page-break-inside: avoid; }
        .mc-sign .sig-line { border-bottom: 1.5px solid #000; height: 56px; }
        .mc-sign .sig-cap  { font-size: 12px; margin-top: 3px; }
        .mc-sign .name { font-size: 17px; font-weight: 700; text-transform: uppercase; margin-top: 14px; }
        .mc-sign .lic  { font-size: 13px; font-weight: 700; }

        .mc-foot { margin-top: 22px; font-family: Arial, sans-serif; font-size: 10px; color: #6b7280; }

        @media print {
            html, body { background: #fff; }
            body { padding: 0; }
            .no-print { display: none !important; }
            .mc-page { box-shadow: none; max-width: none; width: 100%; min-height: 0; padding: 0; break-inside: avoid; page-break-inside: avoid; }
        }

        @media (max-width: 640px) {
            .mc-page { padding: 28px 20px; }
        }
    </style>
</head>
<body>

@php
    use Carbon\Carbon;

    $patient     = $certificate->resolvedPatient();
    $doctor      = $certificate->doctor;
    $appointment = $certificate->appointment;

    // "Last First M." — same order as the clinic's paper form.
    $mi = $patient?->middle_name ? strtoupper(substr($patient->middle_name, 0, 1)) . '.' : '';
    $patientName     = trim(($patient?->last_name ?? '') . ' ' . ($patient?->first_name ?? '') . ' ' . $mi);
    $patientNameShort = trim(($patient?->last_name ?? '') . ' ' . ($patient?->first_name ?? ''));

    $gender   = $patient?->gender;
    $isMale   = $gender === 'Male';
    $isFemale = $gender === 'Female';

    $examDate  = $appointment?->appointment_date ? Carbon::parse($appointment->appointment_date)->format('m-d-Y') : '';
    $issueDate = ($certificate->issued_at ?? now())->format('m-d-Y');

    // Reason ("due to ...") shown on the certificate.
    $reason = match ($certificate->purpose) {
        'School'     => 'School Requirement',
        'Work'       => 'Work Requirement',
        'Sick Leave' => 'Sick Leave',
        default      => $certificate->other_purpose ?: $certificate->purpose,
    };

    // Remarks = the doctor's remarks/recommendation + rest period + extra remarks.
    $remarks = [];
    if ($certificate->recommendation) { $remarks[] = $certificate->recommendation; }
    if ($certificate->rest_start_date && $certificate->rest_end_date) {
        $remarks[] = 'Advised rest from ' . $certificate->rest_start_date->format('F d, Y')
                   . ' to ' . $certificate->rest_end_date->format('F d, Y')
                   . ' (return on ' . $certificate->rest_end_date->copy()->addDay()->format('F d, Y') . ').';
    }
    if ($certificate->additional_remarks) { $remarks[] = $certificate->additional_remarks; }

    // Always show at least two ruled lines, like the paper form.
    while (count($remarks) < 2) { $remarks[] = ''; }

    $doctorName = $doctor ? strtoupper(trim($doctor->first_name . ' ' . $doctor->last_name)) . ', MD' : '';
@endphp

    {{-- Action bar (hidden on print) --}}
    <div class="mc-action-bar no-print">
        <button type="button" class="btn-mc-back" id="mcCloseBtn" data-fallback="{{ url()->previous() }}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="2" width="15" height="15">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            Close
        </button>

        <button type="button" class="btn-mc-print" onclick="window.print()">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="2" width="15" height="15">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M9 16h6v5H9v-5z"/>
            </svg>
            Print / Save as PDF
        </button>
    </div>

    <div class="mc-page" id="mc-printable">

        {{-- HEADER — same as the Prescription print --}}
        <div class="mc-clinic-header">
            <div class="mc-clinic-name">Medical Clinic</div>
            <div class="mc-clinic-specialty">General Practice</div>
            <div class="mc-clinic-address">Brgy. Dayap Calauan, Laguna</div>
            <div class="mc-clinic-hours">
                <span>Mon – Sat &nbsp;9:00 AM – 12:00 PM &nbsp;|&nbsp; 1:00 – 5:00 PM</span>
                <span>Sun – Closed</span>
            </div>
        </div>

        <div class="mc-title">Medical Certificate</div>

        <div class="mc-date">
            <div class="value">{{ $issueDate }}</div>
            <div class="label">Date</div>
        </div>

        <div class="mc-concern">To whom it may concern:</div>

        <p class="mc-text">
            This is to certify that
            <span class="fill">{{ $patientName ?: '—' }}</span>,
            <span class="fill">{{ $patient?->age ?? '—' }}</span> years old,
            <span class="{{ $isMale ? 'sel' : '' }}">male</span>/<span class="{{ $isFemale ? 'sel' : '' }}">female</span>,
            was treated at <span class="fill">Health Medical Clinic</span> on
            <span class="fill">{{ $examDate ?: '—' }}</span> due to
            <span class="fill">{{ $reason }}</span>.
        </p>

        <div class="mc-row">
            <span class="lbl">Final Diagnosis:</span>
            <span class="line">{{ $certificate->medical_statement }}</span>
        </div>

        <div class="mc-remarks-lbl">Remarks:</div>
        @foreach($remarks as $remarkLine)
            <div class="mc-remark-line"><span>{{ $remarkLine }}</span></div>
        @endforeach

        <p class="mc-issued">
            This certification is issued upon the request of
            <span class="{{ $isMale ? 'sel' : '' }}">Mr.</span>/<span class="{{ $isFemale ? 'sel' : '' }}">Mrs.</span>
            <span class="fill">{{ $patientNameShort ?: '—' }}</span>
            for whatever purpose it may serve
            <span class="{{ $isMale ? 'sel' : '' }}">him</span>/<span class="{{ $isFemale ? 'sel' : '' }}">her</span>
            except for medico-legal cases.
        </p>

        {{-- SIGNATURE: intentionally blank — the Doctor signs the printed copy by hand. --}}
        <div class="mc-sign">
            <div class="sig-line"></div>
            <div class="sig-cap">Doctor's Signature</div>
            <div class="name">{{ $doctorName }}</div>
            <div class="lic">License No. {{ $doctor?->license_number ?? '___________' }}</div>
        </div>

    </div>

<script>
    // Opened in its own tab: close it. If the browser won't let the script
    // close the tab, go back to the page the user came from instead.
    (function () {
        var btn = document.getElementById('mcCloseBtn');
        if (!btn) return;
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