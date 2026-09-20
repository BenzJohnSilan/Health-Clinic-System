@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-patient-records.css') }}">
@endsection

@section('content')

<div class="container">

    {{-- ================= HEADER ================= --}}
    <div class="page-header">

        <div class="page-header-top">
            <h1 class="page-title">Patient Records</h1>
            <a href="{{ route('doctor.patient') }}" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Patient List
            </a>
        </div>

        <p class="page-subtitle">View patient's information and appointment history</p>

    </div>

    @php
        $fullName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));

        $initials = collect(explode(' ', $fullName))
            ->filter()
            ->map(fn($word) => strtoupper(substr($word, 0, 1)))
            ->take(2)
            ->implode('');
    @endphp

    <div class="records-layout">

        {{-- ================= PATIENT INFORMATION ================= --}}
        <div class="card patient-info-card">

            <h2 class="card-title">Patient Information</h2>

            <div class="patient-hero">
                <div class="patient-avatar">{{ $initials ?: 'P' }}</div>
                <div class="patient-hero-text">
                    <div class="patient-hero-name">{{ $fullName ?: 'N/A' }}</div>
                    @if($patient['is_walk_in'])
                        <span class="patient-type-badge walkin">Walk-in</span>
                    @else
                        <span class="patient-type-badge registered">Registered</span>
                    @endif
                </div>
            </div>

            <div class="info-list">

                <div class="info-row">
                    <span class="info-label">Age</span>
                    <span class="info-value">{{ $patient['age'] ?? 'N/A' }}</span>
                </div>

                <div class="info-row">
                    <span class="info-label">Gender</span>
                    <span class="info-value">{{ $patient['gender'] ?? 'N/A' }}</span>
                </div>

                <div class="info-row">
                    <span class="info-label">Contact Number</span>
                    <span class="info-value">{{ $patient['contact_number'] ?? 'N/A' }}</span>
                </div>

                <div class="info-row">
                    <span class="info-label">Address</span>
                    <span class="info-value">{{ $patient['address'] ?? 'N/A' }}</span>
                </div>

            </div>

        </div>

        {{-- ================= APPOINTMENT HISTORY ================= --}}
        <div class="card appointment-history-card">

            <div class="appointment-history-header">
                <h2 class="card-title">Appointment History</h2>
                <span class="total-appointments">
                    {{ $appointments->total() }} total appointment{{ $appointments->total() !== 1 ? 's' : '' }}
                </span>
            </div>

            <div class="table-container">
                <table class="record-table">
                    <thead>
                        <tr>
                            <th>Date &amp; Time</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse($appointments as $appointment)
                            @php
                                $statusClass = strtolower(str_replace(' ', '-', $appointment->status));
                            @endphp
                            <tr>
                                <td>
                                    {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('M d, Y') }}
                                    <span class="time-sub">
                                        {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}
                                    </span>
                                </td>
                                <td>{{ $appointment->reason ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $statusClass }}">
                                        {{ $appointment->status }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('doctor.medical-records.show', $appointment->id) }}" class="btn-view">
                                        View Medical Record
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-text">No appointment history found.</td>
                            </tr>
                        @endforelse

                    </tbody>
                </table>
            </div>

            @if($appointments->hasPages())
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Showing <strong>{{ $appointments->firstItem() }}–{{ $appointments->lastItem() }}</strong>
                        of <strong>{{ $appointments->total() }}</strong> appointment{{ $appointments->total() !== 1 ? 's' : '' }}
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

</div>

<!-- ================= FONT AWESOME ================= -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

@endsection