@extends('layouts.staff')

@section('head')
<link rel="stylesheet" href="{{ asset('css/staff-appointments.css') }}">
@endsection

@section('content')

<div class="container">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h1 class="page-title">Appointments</h1>
    </div>

    <!-- ================= ALERTS ================= -->
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <!-- ================= APPOINTMENTS TABLE ================= -->
    <div class="table-container">
        <table class="appointments-table">

            <thead>
                <tr>
                    <th>Patient Name</th>
                    <th>Appointment Schedule</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

                @forelse($appointments as $appointment)

                @php
                    $date = \Carbon\Carbon::parse($appointment->appointment_date);
                    $time = \Carbon\Carbon::parse($appointment->appointment_time);
                @endphp

                <tr>

                    <!-- Patient Name -->
                    <td>
                        {{ $appointment->patientName() }}
                    </td>

                    <!-- Schedule -->
                    <td>
                        {{ $date->format('F d, Y') }}
                        <span style="margin-left:8px; color:#555; font-size:13px;">
                            {{ $time->format('h:i A') }}
                        </span>
                    </td>

                    <!-- Reason -->
                    <td>
                        {{ $appointment->reason ?? '-' }}
                    </td>

                    <!-- Status -->
                    <td>
                        <span class="status {{ str_replace(' ', '-', strtolower($appointment->status)) }}">
                            {{ $appointment->status }}
                        </span>
                    </td>

                    <!-- Action -->
                    <td>
                        <a href="{{ route('staff.appointments.show', $appointment->id) }}" class="btn-view">
                            View
                        </a>
                    </td>

                </tr>

                @empty

                <tr>
                    <td colspan="5"
                        style="text-align:center; font-style:italic; color:#9ca3af; padding: 32px 0;">
                        No appointments found.
                    </td>
                </tr>

                @endforelse

            </tbody>

        </table>
    </div>

    <!-- ================= PAGINATION ================= -->
    <div class="pagination-wrapper">
        <div class="pagination-info">
            @if($appointments->total() > 0)
                Showing <strong>{{ $appointments->firstItem() }}–{{ $appointments->lastItem() }}</strong>
                of <strong>{{ $appointments->total() }}</strong> result{{ $appointments->total() !== 1 ? 's' : '' }}
            @else
                No results found
            @endif
        </div>

        <nav class="pagination-nav" aria-label="Pagination">

            {{-- Previous --}}
            @if($appointments->onFirstPage())
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-left"></i>
                </span>
            @else
                <a class="page-btn" href="{{ $appointments->previousPageUrl() }}">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            @endif

            {{-- Page Numbers --}}
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

            {{-- Next --}}
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

</div>

<!-- ================= FONT AWESOME ================= -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">


@endsection