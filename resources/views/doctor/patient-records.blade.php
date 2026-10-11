@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-patient-records.css') }}">
@endsection

@section('content')

@php
    // Show "—" for anything the patient record does not have.
    $val = fn ($v) => (isset($v) && trim((string) $v) !== '') ? $v : '—';

    $fullName = trim(
        ($patient['first_name'] ?? '') . ' '
        . (!empty($patient['middle_name']) ? $patient['middle_name'] . ' ' : '')
        . ($patient['last_name'] ?? '')
        . (!empty($patient['suffix']) ? ' ' . $patient['suffix'] : '')
    );

    $initials = strtoupper(
        substr($patient['first_name'] ?? '', 0, 1) . substr($patient['last_name'] ?? '', 0, 1)
    );

    $ageGender = collect([
        isset($patient['age']) && $patient['age'] !== '' ? $patient['age'] . ' yrs old' : null,
        $patient['gender'] ?? null,
    ])->filter()->implode(' · ');
@endphp

<div class="container pr-page">

    {{-- ================= HERO ================= --}}
    <div class="pr-hero">
        <div class="pr-hero__left">
            <div class="pr-hero__avatar">{{ $initials ?: 'P' }}</div>
            <div class="pr-hero__text">
                <h1 class="pr-hero__name">{{ $fullName ?: 'N/A' }}</h1>
                <div class="pr-hero__chips">
                    <span class="pr-chip">
                        <i class='bx {{ $patient['is_walk_in'] ? 'bx-walk' : 'bx-user-check' }}'></i>
                        {{ $patient['is_walk_in'] ? 'Walk-in' : 'Registered' }}
                    </span>
                    <span class="pr-chip"><i class='bx bx-id-card'></i> {{ $patient['patient_id'] }}</span>
                    @if($ageGender)
                        <span class="pr-chip"><i class='bx bx-user'></i> {{ $ageGender }}</span>
                    @endif
                </div>
            </div>
        </div>

        <a href="{{ route('doctor.patient') }}" class="pr-back">
            <i class='bx bx-arrow-back'></i> Back to Patient List
        </a>
    </div>

    {{-- ================= PATIENT INFORMATION ================= --}}
    <div class="pr-info-grid">

        {{-- PERSONAL INFORMATION --}}
        <div class="pr-card">
            <div class="pr-card__head">
                <div class="pr-card__icon"><i class='bx bx-user'></i></div>
                <h2>Personal Information</h2>
            </div>

            <dl class="pr-fields pr-fields--2">
                <div><dt>First Name</dt><dd>{{ $val($patient['first_name']) }}</dd></div>
                <div><dt>Middle Name</dt><dd>{{ $val($patient['middle_name']) }}</dd></div>
                <div><dt>Last Name</dt><dd>{{ $val($patient['last_name']) }}</dd></div>
                <div><dt>Suffix</dt><dd>{{ $val($patient['suffix']) }}</dd></div>
                <div><dt>Birthdate</dt><dd>{{ $val($patient['birthdate']) }}</dd></div>
                <div><dt>Age</dt><dd>{{ $val($patient['age']) }}</dd></div>
                <div><dt>Gender</dt><dd>{{ $val($patient['gender']) }}</dd></div>
                <div><dt>Civil Status</dt><dd>{{ $val($patient['civil_status']) }}</dd></div>
                <div><dt>Contact Number</dt><dd>{{ $val($patient['contact_number']) }}</dd></div>
                @unless($patient['is_walk_in'])
                    <div><dt>Email</dt><dd>{{ $val($patient['email']) }}</dd></div>
                @endunless
                <div class="pr-fields__wide"><dt>Complete Address</dt><dd>{{ $val($patient['address']) }}</dd></div>
            </dl>
        </div>

        {{-- MEDICAL + EMERGENCY --}}
        <div class="pr-stack">

            <div class="pr-card">
                <div class="pr-card__head">
                    <div class="pr-card__icon pr-card__icon--red"><i class='bx bx-plus-medical'></i></div>
                    <h2>Medical Information</h2>
                </div>

                <dl class="pr-fields pr-fields--2">
                    <div><dt>Blood Type</dt><dd>{{ $val($patient['blood_type']) }}</dd></div>
                    <div class="pr-fields__wide"><dt>Allergies</dt><dd>{{ $val($patient['allergies']) }}</dd></div>
                </dl>
            </div>

            <div class="pr-card">
                <div class="pr-card__head">
                    <div class="pr-card__icon pr-card__icon--amber"><i class='bx bx-phone-call'></i></div>
                    <h2>Emergency Contact</h2>
                </div>

                <dl class="pr-fields pr-fields--2">
                    <div><dt>Name</dt><dd>{{ $val($patient['emergency_name']) }}</dd></div>
                    <div><dt>Relationship</dt><dd>{{ $val($patient['relationship']) }}</dd></div>
                    <div class="pr-fields__wide"><dt>Contact Number</dt><dd>{{ $val($patient['emergency_contact']) }}</dd></div>
                    <div class="pr-fields__wide"><dt>Address</dt><dd>{{ $val($patient['emergency_address']) }}</dd></div>
                </dl>
            </div>

        </div>
    </div>

    {{-- ================= APPOINTMENT HISTORY ================= --}}
    <div class="pr-card pr-history">

        <div class="pr-card__head pr-card__head--between">
            <div class="pr-card__head-left">
                <div class="pr-card__icon"><i class='bx bx-calendar-check'></i></div>
                <h2>Appointment History</h2>
            </div>
            <span class="pr-count">
                {{ $appointments->total() }} total appointment{{ $appointments->total() !== 1 ? 's' : '' }}
            </span>
        </div>

        <div class="pr-table-wrap">
            <table class="pr-table">
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
                                <span class="pr-date">{{ \Carbon\Carbon::parse($appointment->appointment_date)->format('M d, Y') }}</span>
                                <span class="pr-time">{{ $appointment->timeLabel('h:i A') }}</span>
                            </td>
                            <td>{{ $appointment->reason ?: '—' }}</td>
                            <td><span class="pr-badge {{ $statusClass }}">{{ $appointment->status }}</span></td>
                            <td>
                                <a href="{{ route('doctor.medical-records.show', $appointment->id) }}"
                                   class="pr-btn" target="_blank" rel="noopener noreferrer">
                                    <i class='bx bx-file'></i> View Medical Record
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="pr-empty">No appointment history found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ================= PAGINATION ================= --}}
        @if($appointments->hasPages())
            <div class="pr-pagination">
                <div class="pr-pagination__info">
                    Showing <strong>{{ $appointments->firstItem() }}–{{ $appointments->lastItem() }}</strong>
                    of <strong>{{ $appointments->total() }}</strong> appointment{{ $appointments->total() !== 1 ? 's' : '' }}
                </div>

                <nav class="pr-pagination__nav" aria-label="Pagination">

                    @if($appointments->onFirstPage())
                        <span class="pr-pgbtn disabled"><i class="fa-solid fa-chevron-left"></i></span>
                    @else
                        <a class="pr-pgbtn" href="{{ $appointments->previousPageUrl() }}"><i class="fa-solid fa-chevron-left"></i></a>
                    @endif

                    @php
                        $currentPage = $appointments->currentPage();
                        $lastPage    = $appointments->lastPage();

                        $pages = collect(range(1, $lastPage))->filter(function ($p) use ($currentPage, $lastPage) {
                            return $p === 1 || $p === $lastPage || abs($p - $currentPage) <= 2;
                        })->values();

                        $prev = null;
                    @endphp

                    @foreach($pages as $page)
                        @if($prev !== null && $page - $prev > 1)
                            <span class="pr-ellipsis">…</span>
                        @endif

                        @if($page === $currentPage)
                            <span class="pr-pgbtn active">{{ $page }}</span>
                        @else
                            <a class="pr-pgbtn" href="{{ $appointments->url($page) }}">{{ $page }}</a>
                        @endif

                        @php $prev = $page; @endphp
                    @endforeach

                    @if($appointments->hasMorePages())
                        <a class="pr-pgbtn" href="{{ $appointments->nextPageUrl() }}"><i class="fa-solid fa-chevron-right"></i></a>
                    @else
                        <span class="pr-pgbtn disabled"><i class="fa-solid fa-chevron-right"></i></span>
                    @endif

                </nav>
            </div>
        @endif

    </div>

</div>

@endsection