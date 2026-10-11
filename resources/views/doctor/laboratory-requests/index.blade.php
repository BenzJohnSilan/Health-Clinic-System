@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-laboratory.css') }}">
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
        <h1 class="page-title">Laboratory Results</h1>
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    {{-- ================= SEARCH + FILTERS ================= --}}
    <form method="GET" action="{{ route('doctor.laboratory-requests.index') }}" class="lab-toolbar">
        <div class="lab-search-box">
            <i class='bx bx-search'></i>
            <input type="text" name="search" placeholder="Search patient or reference no..." value="{{ $search }}">
        </div>

        <select name="status" class="lab-select" onchange="this.form.submit()">
            <option value="All" {{ $statusFilter === 'All' ? 'selected' : '' }}>All Statuses</option>
            <option value="Awaiting Result" {{ $statusFilter === 'Awaiting Result' ? 'selected' : '' }}>Awaiting Result</option>
            <option value="Result Received" {{ $statusFilter === 'Result Received' ? 'selected' : '' }}>Result Received</option>
            <option value="Correction Required" {{ $statusFilter === 'Correction Required' ? 'selected' : '' }}>Correction Required</option>
            <option value="Reviewed" {{ $statusFilter === 'Reviewed' ? 'selected' : '' }}>Reviewed</option>
        </select>

        <select name="date_filter" class="lab-select" onchange="this.form.submit()">
            <option value="All Dates" {{ $dateFilter === 'All Dates' ? 'selected' : '' }}>All Dates</option>
            <option value="Today" {{ $dateFilter === 'Today' ? 'selected' : '' }}>Today</option>
            <option value="This Week" {{ $dateFilter === 'This Week' ? 'selected' : '' }}>This Week</option>
            <option value="This Month" {{ $dateFilter === 'This Month' ? 'selected' : '' }}>This Month</option>
        </select>

        <button type="submit" class="lab-search-btn">
            <i class='bx bx-filter-alt'></i> Filter
        </button>
    </form>

    {{-- ================= TABLE ================= --}}
    <div class="table-container">
        <table class="record-table">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Requested Tests</th>
                    <th>Date Requested</th>
                    <th>Result Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($laboratoryRequests as $labRequest)
                    @php
                        $statusClassMap = [
                            'Awaiting Result'     => 'awaiting',
                            'Result Received'     => 'received',
                            'Correction Required' => 'correction',
                            'Reviewed'            => 'reviewed',
                        ];
                    @endphp
                    <tr>
                        <td>{{ $labRequest->patientName() }}</td>
                        <td class="lab-tests-cell">{{ $labRequest->testsDisplay() }}</td>
                        <td>{{ $labRequest->requested_at?->format('M d, Y') ?? '—' }}</td>
                        <td>{{ $labRequest->result_received_at?->format('M d, Y') ?? '—' }}</td>
                        <td>
                            <span class="status {{ $statusClassMap[$labRequest->status] ?? 'awaiting' }}">
                                {{ $labRequest->status }}
                            </span>
                        </td>
                        <td>
                            @if($labRequest->isResultReceived())
                                <a href="{{ route('doctor.laboratory-requests.show', $labRequest->id) }}" class="btn-review">
                                    Review
                                </a>
                            @elseif($labRequest->isCorrectionRequired())
                                <a href="{{ route('doctor.laboratory-requests.show', $labRequest->id) }}" class="btn-view">
                                    View
                                </a>
                            @elseif($labRequest->isReviewed())
                                <a href="{{ route('doctor.laboratory-requests.show', $labRequest->id) }}" class="btn-view">
                                    View
                                </a>
                            @else
                                <a href="{{ route('doctor.appointments.show', $labRequest->appointment_id) }}" class="btn-view">
                                    View Request
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="no-data">No laboratory requests found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- ================= PAGINATION ================= -->
    <div class="pagination-wrapper">
        <div class="pagination-info">
            @if($laboratoryRequests->total() > 0)
                Showing <strong>{{ $laboratoryRequests->firstItem() }}–{{ $laboratoryRequests->lastItem() }}</strong>
                of <strong>{{ $laboratoryRequests->total() }}</strong> result{{ $laboratoryRequests->total() !== 1 ? 's' : '' }}
            @else
                No results found
            @endif
        </div>

        <nav class="pagination-nav" aria-label="Pagination">

            {{-- Previous --}}
            @if($laboratoryRequests->onFirstPage())
                <span class="page-btn disabled"><i class="fa-solid fa-chevron-left"></i></span>
            @else
                <a class="page-btn" href="{{ $laboratoryRequests->previousPageUrl() }}"><i class="fa-solid fa-chevron-left"></i></a>
            @endif

            @php
                $currentPage = $laboratoryRequests->currentPage();
                $lastPage    = $laboratoryRequests->lastPage();
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
                    <a class="page-btn" href="{{ $laboratoryRequests->url($page) }}">{{ $page }}</a>
                @endif

                @php $prev = $page; @endphp
            @endforeach

            {{-- Next --}}
            @if($laboratoryRequests->hasMorePages())
                <a class="page-btn" href="{{ $laboratoryRequests->nextPageUrl() }}"><i class="fa-solid fa-chevron-right"></i></a>
            @else
                <span class="page-btn disabled"><i class="fa-solid fa-chevron-right"></i></span>
            @endif

        </nav>
    </div>

</div>

@endsection