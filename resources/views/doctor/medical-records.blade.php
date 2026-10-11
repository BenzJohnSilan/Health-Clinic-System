@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-medical-records.css') }}">
@endsection

@section('content')

<div class="container">

    <!-- ================= HERO ================= -->
    <div class="mr-hero">
        <div class="mr-hero__main">
            <div class="mr-hero__icon"><i class='bx bx-folder-open'></i></div>
            <div>
                <h1 class="mr-hero__title">Medical Records</h1>
                <p class="mr-hero__sub">Browse the clinical records of your consultations.</p>
            </div>
        </div>
        <div class="mr-hero__count">
            <span class="mr-hero__count-num">{{ $records->total() }}</span>
            <span class="mr-hero__count-label">Total Record{{ $records->total() !== 1 ? 's' : '' }}</span>
        </div>
    </div>

    <!-- ================= ALERTS ================= -->
    @if(session('success'))
        <div class="alert-success"><i class='bx bx-check-circle'></i> {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error"><i class='bx bx-error-circle'></i> {{ session('error') }}</div>
    @endif

    <!-- ================= RECORDS CARD ================= -->
    <div class="info-card">

        <div class="info-card-header">
            <div class="info-card-title">
                <span class="info-card-icon"><i class='bx bx-list-ul'></i></span>
                <h3>Consultation Records</h3>
            </div>
        </div>

        <div class="table-container">
            <table class="record-table">
                <thead>
                    <tr>
                        <th>Patient Name</th>
                        <th>Appointment Date</th>
                        <th>Diagnosis</th>
                        <th>Treatment</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($records as $record)
                        @php
                            $mrName = optional($record->appointment)->patientName() ?? 'N/A';
                            $mrInitials = collect(explode(' ', $mrName))
                                ->filter()
                                ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                                ->take(2)
                                ->implode('');
                        @endphp
                        <tr>
                            <td>
                                <div class="cell-patient">
                                    <span class="cell-avatar">{{ $mrInitials ?: 'P' }}</span>
                                    <span class="cell-name">{{ $mrName }}</span>
                                </div>
                            </td>

                            <td class="cell-date">
                                @if(optional($record->appointment)->appointment_date)
                                    {{ \Carbon\Carbon::parse($record->appointment->appointment_date)->format('M d, Y') }}
                                @else
                                    N/A
                                @endif
                            </td>

                            <td><div class="cell-clamp">{{ $record->diagnosis ?? 'N/A' }}</div></td>

                            <td><div class="cell-clamp">{{ $record->treatment ?? 'N/A' }}</div></td>

                            <td>
                                @if($record->appointment)
                                    <a href="{{ route('doctor.medical-records.show', $record->appointment->id) }}" class="btn-view">
                                        <i class='bx bx-show'></i> View
                                    </a>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="no-data">
                                <div class="empty-block">
                                    <i class='bx bx-folder-open'></i>
                                    <p>No medical records found.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    <!-- ================= PAGINATION ================= -->
    <div class="pagination-wrapper">
        <div class="pagination-info">
            @if($records->total() > 0)
                Showing <strong>{{ $records->firstItem() }}–{{ $records->lastItem() }}</strong>
                of <strong>{{ $records->total() }}</strong> result{{ $records->total() !== 1 ? 's' : '' }}
            @else
                No results found
            @endif
        </div>

        <nav class="pagination-nav" aria-label="Pagination">

            {{-- Previous --}}
            @if($records->onFirstPage())
                <span class="page-btn disabled">
                    <i class='bx bx-chevron-left'></i>
                </span>
            @else
                <a class="page-btn" href="{{ $records->previousPageUrl() }}">
                    <i class='bx bx-chevron-left'></i>
                </a>
            @endif

            {{-- Page Numbers --}}
            @php
                $currentPage = $records->currentPage();
                $lastPage    = $records->lastPage();

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
                    <a class="page-btn" href="{{ $records->url($page) }}">{{ $page }}</a>
                @endif

                @php $prev = $page; @endphp
            @endforeach

            {{-- Next --}}
            @if($records->hasMorePages())
                <a class="page-btn" href="{{ $records->nextPageUrl() }}">
                    <i class='bx bx-chevron-right'></i>
                </a>
            @else
                <span class="page-btn disabled">
                    <i class='bx bx-chevron-right'></i>
                </span>
            @endif

        </nav>
    </div>

    </div>{{-- end info-card --}}

</div>

@endsection