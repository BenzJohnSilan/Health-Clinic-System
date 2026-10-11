@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-medical-certificates.css') }}">
<style>
    /* ===== Pagination — attached to the table, same as the Patient List ===== */
    .pagination-wrapper {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        padding: 14px 18px;
        background: #fff;
        border: 1px solid #f0e6ff;
        border-top: none;
        border-radius: 0 0 12px 12px;
        box-shadow: 0 3px 12px rgba(0, 0, 0, 0.07);
        margin-bottom: 30px;
    }

    .pagination-info { font-size: 13px; color: #6b7280; }
    .pagination-info strong { color: #374151; font-weight: 600; }

    .pagination-nav { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }

    .page-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        height: 34px;
        padding: 0 10px;
        border-radius: 8px;
        border: 1.5px solid #e9d5ff;
        background: #fff;
        color: #6a0dad;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s ease;
        line-height: 1;
    }

    .page-btn:hover { background: #f3e8ff; border-color: #c084fc; color: #6a0dad; }

    .page-btn.active {
        background: #6a0dad;
        border-color: #6a0dad;
        color: #fff;
        box-shadow: 0 2px 8px rgba(106, 13, 173, 0.3);
        cursor: default;
        pointer-events: none;
    }

    .page-btn.disabled {
        color: #d1d5db;
        border-color: #f3f4f6;
        background: #f9fafb;
        cursor: not-allowed;
        pointer-events: none;
    }

    .page-ellipsis {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        height: 34px;
        font-size: 13px;
        color: #9ca3af;
    }

    @media screen and (max-width: 600px) {
        .pagination-wrapper { flex-direction: column; align-items: center; }
    }
</style>
@endsection

@section('content')

<div class="container">

    <div class="page-header">
        <h1 class="page-title">Medical Certificates</h1>
        <a href="{{ route('doctor.medical-certificates.create') }}" class="btn-create">
            <i class='bx bx-plus'></i> Create Medical Certificate
        </a>
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <!-- ================= FILTER TABS ================= -->
    <div class="filter-tabs">
        <a href="{{ route('doctor.medical-certificates.index', ['status' => 'all']) }}"
           class="filter-tab {{ $filter === 'all' ? 'active' : '' }}">All</a>
        <a href="{{ route('doctor.medical-certificates.index', ['status' => 'issued']) }}"
           class="filter-tab {{ $filter === 'issued' ? 'active' : '' }}">Issued</a>
        <a href="{{ route('doctor.medical-certificates.index', ['status' => 'released']) }}"
           class="filter-tab {{ $filter === 'released' ? 'active' : '' }}">Released</a>
    </div>

    <!-- ================= TABLE ================= -->
    <div class="table-container">
        <table class="record-table">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Certificate No.</th>
                    <th>Purpose</th>
                    <th>Date Examined</th>
                    <th>Status</th>
                    <th>Invoice</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($certificates as $certificate)
                    <tr>
                        <td>{{ $certificate->resolvedPatientName() }}</td>
                        <td>{{ $certificate->certificate_number ?? '—' }}</td>
                        <td>{{ $certificate->displayPurpose() }}</td>
                        <td>
                            {{ optional($certificate->appointment)->appointment_date
                                ? \Carbon\Carbon::parse($certificate->appointment->appointment_date)->format('M d, Y')
                                : '—' }}
                        </td>
                        <td>
                            <span class="status {{ $certificate->status }}">
                                {{ $certificate->isCorrectionRequested() ? 'Correction Requested' : ucfirst($certificate->status) }}
                            </span>
                        </td>
                        <td>
                            @if($certificate->invoice)
                                {{ $certificate->invoice->invoice_no }}
                                <br><small>{{ $certificate->invoice->status }}</small>
                            @else
                                <small>No invoice</small>
                            @endif
                        </td>
                        <td>
                            @if($certificate->isCorrectionRequested())
                                <a href="{{ route('doctor.medical-certificates.correction.review', $certificate->id) }}" class="btn-review">
                                    Review Correction
                                </a>
                            @else
                                <a href="{{ route('doctor.medical-certificates.show', $certificate->id) }}" class="btn-view">
                                    View
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="no-data">No medical certificates yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- ================= PAGINATION ================= -->
    <div class="pagination-wrapper">
        <div class="pagination-info">
            @if($certificates->total() > 0)
                Showing <strong>{{ $certificates->firstItem() }}–{{ $certificates->lastItem() }}</strong>
                of <strong>{{ $certificates->total() }}</strong> result{{ $certificates->total() !== 1 ? 's' : '' }}
            @else
                No results found
            @endif
        </div>

        <nav class="pagination-nav" aria-label="Pagination">

            {{-- Previous --}}
            @if($certificates->onFirstPage())
                <span class="page-btn disabled"><i class="fa-solid fa-chevron-left"></i></span>
            @else
                <a class="page-btn" href="{{ $certificates->previousPageUrl() }}"><i class="fa-solid fa-chevron-left"></i></a>
            @endif

            @php
                $currentPage = $certificates->currentPage();
                $lastPage    = $certificates->lastPage();
                $pages = collect(range(1, max($lastPage, 1)))->filter(function ($p) use ($currentPage, $lastPage) {
                    return $p === 1 || $p === $lastPage || abs($p - $currentPage) <= 2;
                })->values();
                $prev = null;
            @endphp

            {{-- Page numbers --}}
            @foreach($pages as $page)
                @if($prev !== null && $page - $prev > 1)
                    <span class="page-ellipsis">…</span>
                @endif

                @if($page === $currentPage)
                    <span class="page-btn active">{{ $page }}</span>
                @else
                    <a class="page-btn" href="{{ $certificates->url($page) }}">{{ $page }}</a>
                @endif

                @php $prev = $page; @endphp
            @endforeach

            {{-- Next --}}
            @if($certificates->hasMorePages())
                <a class="page-btn" href="{{ $certificates->nextPageUrl() }}"><i class="fa-solid fa-chevron-right"></i></a>
            @else
                <span class="page-btn disabled"><i class="fa-solid fa-chevron-right"></i></span>
            @endif

        </nav>
    </div>

</div>

@endsection