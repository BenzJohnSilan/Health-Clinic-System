@extends('layouts.staff')

@section('head')
<link rel="stylesheet" href="{{ asset('css/staff-patient-appointments.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endsection

@section('content')

<div class="container">

    @php
        $fullName = trim($patientInfo['full_name']);
        $nameParts = collect(explode(' ', $fullName))->filter();
        $initials = $nameParts->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
        $initials = $initials !== '' ? $initials : '?';
    @endphp

    {{-- Back Button --}}
    <a href="{{ route('staff.patients.index') }}" class="back-btn">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Back to Patients</span>
    </a>

    <div class="page-heading">
        <div class="page-heading-icon">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div>
            <h1 class="page-title">Patient Appointments</h1>
            <p class="page-subtitle">Appointment history for this patient</p>
        </div>
    </div>

    {{-- ================= PATIENT INFO ================= --}}
    <div class="details-card patient-summary-card">
        <div class="patient-info-row">
            <div class="patient-identity">
                <div class="avatar">{{ $initials }}</div>
                <div>
                    <div class="patient-name">{{ $patientInfo['full_name'] }}</div>
                    <div class="patient-id">Patient ID: #{{ $patientInfo['raw_id'] }}</div>
                </div>
            </div>

            @if($patientInfo['is_walk_in'])
                <span class="type-badge walkin">
                    <i class="fa-solid fa-shoe-prints"></i> Walk-in
                </span>
            @else
                <span class="type-badge registered">
                    <i class="fa-solid fa-id-card"></i> Registered
                </span>
            @endif
        </div>
    </div>

    {{-- ================= APPOINTMENT HISTORY ================= --}}
    <div class="details-card table-card">
        <div class="card-header">
            <h2>Appointment History</h2>
        </div>

        <div class="table-container">
            <table class="patient-appointments-table">

                <colgroup>
                    <col class="col-datetime">
                    <col class="col-doctor">
                    <col class="col-reason">
                    <col class="col-status">
                </colgroup>

                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>Doctor</th>
                        <th>Reason</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($appointments as $appointment)
                        @php
                            $date = \Carbon\Carbon::parse($appointment->appointment_date);
                            $time = \Carbon\Carbon::parse($appointment->appointment_time);
                        @endphp
                        <tr>
                            <td>
                                {{ $date->format('F d, Y') }}
                                <span class="time-inline">{{ $time->format('h:i A') }}</span>
                            </td>
                            <td>
                                Dr. {{ $appointment->doctor->first_name ?? '' }} {{ $appointment->doctor->last_name ?? '' }}
                            </td>
                            <td>
                                <span class="reason-preview">{{ $appointment->reason ?? '-' }}</span>
                            </td>
                            <td>
                                <span class="status {{ str_replace(' ', '-', strtolower($appointment->status)) }}">
                                    <i class="fa-solid fa-circle"></i>
                                    {{ $appointment->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty-row">
                                <i class="fa-regular fa-calendar-xmark"></i>
                                <span>No appointment history found for this patient.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>

        {{-- ================= PAGINATION ================= --}}
        @if($appointments->hasPages())
        <div class="pagination-wrapper">
            <div class="pagination-info">
                Showing <strong>{{ $appointments->firstItem() }}–{{ $appointments->lastItem() }}</strong>
                of <strong>{{ $appointments->total() }}</strong> result{{ $appointments->total() !== 1 ? 's' : '' }}
            </div>

            <nav class="pagination-nav" aria-label="Pagination">

                @if($appointments->onFirstPage())
                    <span class="page-btn disabled">
                        <i class="fa-solid fa-chevron-left"></i>
                    </span>
                @else
                    <a class="page-btn" href="{{ $appointments->previousPageUrl() }}">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                @endif

                @php
                    $currentPage = $appointments->currentPage();
                    $lastPage    = $appointments->lastPage();

                    $pages = collect(range(1, $lastPage))->filter(function ($p) use ($currentPage, $lastPage) {
                        return $p === 1
                            || $p === $lastPage
                            || abs($p - $currentPage) <= 2;
                    })->values();
                @endphp

                @php $prev = null; @endphp
                @foreach($pages as $page)
                    @if($prev !== null && $page - $prev > 1)
                        <span class="page-ellipsis">…</span>
                    @endif

                    @if($page === $currentPage)
                        <span class="page-btn active">{{ $page }}</span>
                    @else
                        <a class="page-btn" href="{{ $appointments->url($page) }}">{{ $page }}</a>
                    @endif

                    @php $prev = $page; @endphp
                @endforeach

                @if($appointments->hasMorePages())
                    <a class="page-btn" href="{{ $appointments->nextPageUrl() }}">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                @else
                    <span class="page-btn disabled">
                        <i class="fa-solid fa-chevron-right"></i>
                    </span>
                @endif

            </nav>
        </div>
        @endif
    </div>

</div>

@endsection