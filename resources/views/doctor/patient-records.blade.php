@extends('layouts.doctor')

@section('head')
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap');

* {
    font-family: 'DM Sans', sans-serif;
    box-sizing: border-box;
}

/* ================= PAGE HEADER ================= */
.page-header {
    margin-bottom: 28px;
}

.page-title {
    font-size: 24px;
    font-weight: 600;
    color: #0f172a;
    letter-spacing: -0.3px;
    margin: 0 0 4px 0;
}

.page-subtitle {
    font-size: 13px;
    color: #64748b;
    margin: 0;
}

/* ================= SECTION LABEL ================= */
.section-label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #94a3b8;
    margin-bottom: 10px;
}

/* ================= PATIENT INFO TABLE ================= */
.info-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 32px;
}

.info-table {
    width: 100%;
    border-collapse: collapse;
}

.info-table tr {
    border-bottom: 1px solid #f1f5f9;
}

.info-table tr:last-child {
    border-bottom: none;
}

.info-table td {
    padding: 11px 16px;
    font-size: 13.5px;
    vertical-align: top;
    line-height: 1.5;
}

.info-table td:first-child {
    width: 200px;
    color: #64748b;
    font-weight: 500;
    white-space: nowrap;
    background: #f8fafc;
    border-right: 1px solid #f1f5f9;
}

.info-table td:last-child {
    color: #0f172a;
}

.badge-blood {
    display: inline-block;
    background: #fee2e2;
    color: #b91c1c;
    font-size: 12px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 4px;
    font-family: 'DM Mono', monospace;
}

/* ================= FINDINGS CARD ================= */
.findings-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}

.findings-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.findings-title {
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.findings-title svg {
    color: #3b82f6;
    flex-shrink: 0;
}

.findings-count {
    font-size: 12px;
    color: #94a3b8;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    padding: 2px 10px;
    border-radius: 20px;
}

/* ================= FINDINGS TABLE ================= */
.findings-table {
    width: 100%;
    border-collapse: collapse;
}

.findings-table thead tr {
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
}

.findings-table th {
    padding: 11px 16px;
    font-size: 11.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #64748b;
    text-align: left;
}

.findings-table th.col-center {
    text-align: center;
}

.findings-table tbody tr {
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s ease;
}

.findings-table tbody tr:last-child {
    border-bottom: none;
}

.findings-table tbody tr:hover {
    background: #fafbff;
}

.findings-table td {
    padding: 13px 16px;
    font-size: 13.5px;
    color: #1e293b;
    vertical-align: middle;
}

.findings-table td.col-center {
    text-align: center;
}

.appt-id {
    font-family: 'DM Mono', monospace;
    font-size: 12.5px;
    font-weight: 500;
    color: #3b82f6;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    padding: 3px 9px;
    border-radius: 5px;
    display: inline-block;
}

.diagnosis-text {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.5;
    color: #374151;
    max-width: 380px;
}

.date-main {
    font-size: 13.5px;
    color: #1e293b;
}

.date-time {
    font-size: 11.5px;
    color: #94a3b8;
    margin-top: 2px;
}

.btn-view {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 14px;
    background: #0f172a;
    color: #fff !important;
    border-radius: 6px;
    font-size: 12.5px;
    font-weight: 500;
    text-decoration: none !important;
    transition: background 0.15s ease, transform 0.1s ease;
    white-space: nowrap;
}

.btn-view:hover {
    background: #1e293b;
    transform: translateY(-1px);
}

.btn-view svg {
    width: 13px;
    height: 13px;
    flex-shrink: 0;
}

/* ================= EMPTY STATE ================= */
.empty-state {
    padding: 52px 24px;
    text-align: center;
    color: #94a3b8;
}

.empty-state svg {
    width: 40px;
    height: 40px;
    margin: 0 auto 12px;
    opacity: 0.35;
    display: block;
}

.empty-state p {
    font-size: 14px;
    margin: 0;
}
</style>
@endsection

@section('content')

{{-- ================= PAGE HEADER ================= --}}
<div class="page-header">
    <h2 class="page-title">Patient Records</h2>
    <p class="page-subtitle">
        Viewing records for {{ $patient->first_name }} {{ $patient->last_name }}
    </p>
</div>

{{-- ================= PATIENT INFORMATION ================= --}}
<p class="section-label">Patient Information</p>

<div class="info-card">
    <table class="info-table">
        <tr>
            <td>Full Name</td>
            <td>
                {{ $patient->first_name }}
                {{ $patient->middle_name ? $patient->middle_name . ' ' : '' }}
                {{ $patient->last_name }}
                {{ $patient->suffix ?? '' }}
            </td>
        </tr>
        <tr>
            <td>Gender</td>
            <td>{{ $patient->gender ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td>Date of Birth</td>
            <td>
                {{ $patient->birthdate
                    ? \Carbon\Carbon::parse($patient->birthdate)->format('F d, Y')
                    : 'N/A' }}
            </td>
        </tr>
        <tr>
            <td>Address</td>
            <td>{{ $patient->address ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td>Contact Number</td>
            <td>{{ $patient->contact_number ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td>Blood Type</td>
            <td>
                @if($patient->blood_type)
                    <span class="badge-blood">{{ $patient->blood_type }}</span>
                @else
                    N/A
                @endif
            </td>
        </tr>
        <tr>
            <td>Allergies</td>
            <td>{{ $patient->allergies ?? 'None' }}</td>
        </tr>
        <tr>
            <td>Emergency Contact</td>
            <td>
                {{ $patient->emergency_name ?? 'N/A' }}
                @if($patient->relationship)
                    <span style="color:#94a3b8; font-size:12.5px;">({{ $patient->relationship }})</span>
                @endif
            </td>
        </tr>
        <tr>
            <td>Emergency Number</td>
            <td>{{ $patient->emergency_contact_number ?? 'N/A' }}</td>
        </tr>
    </table>
</div>

{{-- ================= FINDINGS ================= --}}
<p class="section-label">Findings</p>

<div class="findings-card">

    <div class="findings-header">
        <p class="findings-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                fill="none" viewBox="0 0 24 24" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 12h6M9 16h6M9 8h6"/>
                <path d="M5 20h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z"/>
            </svg>
            Consultation Records
        </p>
        <span class="findings-count">
            {{ $records->count() }} {{ Str::plural('record', $records->count()) }}
        </span>
    </div>

    @if($records->count() > 0)

    <table class="findings-table">
        <thead>
            <tr>
                <th>Appointment ID</th>
                <th>Diagnosis</th>
                <th>Date of Consultation</th>
                <th class="col-center">Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $record)
            <tr>

                {{-- Appointment ID --}}
                <td>
                    <span class="appt-id">#{{ $record->id }}</span>
                </td>

                {{-- Diagnosis --}}
                <td>
                    <div class="diagnosis-text">
                        {{ $record->diagnosis }}
                    </div>
                </td>

                {{-- Date of Consultation --}}
                <td>
                    <div class="date-main">
                        {{ \Carbon\Carbon::parse($record->appointment_date)->format('M d, Y') }}
                    </div>
                    <div class="date-time">
                        {{ \Carbon\Carbon::parse($record->appointment_time)->format('g:i A') }}
                    </div>
                </td>

                {{-- Action --}}
                <td class="col-center">
                    <a href="{{ route('doctor.appointments.show', $record->id) }}"
                       class="btn-view">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        View
                    </a>
                </td>

            </tr>
            @endforeach
        </tbody>
    </table>

    @else

    <div class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
            stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M9 12h6m-3-3v6M4 6h16M4 18h16"/>
        </svg>
        <p>No consultation records found for this patient.</p>
    </div>

    @endif

</div>

@endsection