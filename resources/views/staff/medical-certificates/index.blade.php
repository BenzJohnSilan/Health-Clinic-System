@extends('layouts.staff')

@section('head')
<link rel="stylesheet" href="{{ asset('css/staff-patient.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* Intro text under the page title */
    .mc-intro {
        font-size: 13px;
        color: #6b7280;
        margin: -10px 0 16px;
        line-height: 1.6;
    }

    /* Small helper line inside a table cell */
    .mc-note {
        font-size: 12px;
        color: #6b7280;
        margin-top: 4px;
    }

    /* Certificate status pill (same shape as the Patient List type badges) */
    .mc-pill {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.78rem;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }
    .mc-pill--issued   { background: #dbeafe; color: #1e40af; }
    .mc-pill--released { background: #d1fae5; color: #065f46; }

    /* Invoice payment pill */
    .pay-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.78rem;
        letter-spacing: 0.3px;
        white-space: nowrap;
        text-transform: uppercase;
    }
    .pay-badge--paid      { background: #d1fae5; color: #065f46; }
    .pay-badge--partial   { background: #fef3c7; color: #92400e; }
    .pay-badge--unpaid    { background: #fee2e2; color: #991b1b; }
    .pay-badge--cancelled { background: #e5e7eb; color: #374151; }

    /* Invoice number chip (link when the staff can open billing) */
    .invoice-chip {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 8px;
        background: #ede9fe;
        color: #5b21b6;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
        transition: background-color 0.2s ease;
    }
    a.invoice-chip:hover { background: #ddd6fe; }

    /* Release button */
    .btn-release {
        background: #10b981;
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 7px 14px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        font-family: "Poppins", Arial, sans-serif;
        white-space: nowrap;
        transition: background-color 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease;
    }
    .btn-release:hover {
        background: #059669;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
    }
    .btn-release[disabled] {
        background: #e5e7eb;
        color: #9ca3af;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    .btn-release--done i { margin-right: 4px; }
</style>
@endsection

@section('content')
<div class="container">

    @php
        $activeFilters = collect(['search' => request('search'), 'status' => $filter])
            ->filter(fn ($v) => $v !== null && $v !== '' && $v !== 'all');
    @endphp

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h1 class="page-title">Medical Certificates</h1>
    </div>

    <!-- ================= ALERTS ================= -->
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error"><ul><li>{{ session('error') }}</li></ul></div>
    @endif

    <!-- ================= SEARCH & FILTER ================= -->
    <form method="GET" action="{{ route('staff.medical-certificates.index') }}" class="filter-bar">
        <input
            type="text"
            name="search"
            class="filter-input"
            placeholder="Search patient or certificate no...."
            value="{{ request('search') }}">

        <select name="status" class="filter-input" onchange="this.form.submit()">
            <option value="all"              {{ $filter === 'all' ? 'selected' : '' }}>All</option>
            <option value="awaiting_payment" {{ $filter === 'awaiting_payment' ? 'selected' : '' }}>Awaiting Payment</option>
            <option value="ready"            {{ $filter === 'ready' ? 'selected' : '' }}>Paid – Ready to Release</option>
            <option value="released"         {{ $filter === 'released' ? 'selected' : '' }}>Released</option>
        </select>

        <button type="submit" class="btn-filter">
            <i class="fa-solid fa-filter"></i> Filter
        </button>

        @if($activeFilters->isNotEmpty())
            <a href="{{ route('staff.medical-certificates.index') }}" class="btn-clear-filter">Clear</a>
        @endif
    </form>

    <!-- ================= CERTIFICATES TABLE ================= -->
    <div class="table-container">
        <table class="patients-table">
            <thead>
                <tr>
                    <th>Certificate No.</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Invoice</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($certificates as $certificate)
                    @php $invoice = $certificate->invoice; @endphp
                    <tr>
                        <td>{{ $certificate->certificate_number ?? '—' }}</td>
                        <td>{{ $certificate->resolvedPatientName() }}</td>
                        <td>{{ $certificate->doctor ? 'Dr. ' . $certificate->doctor->first_name . ' ' . $certificate->doctor->last_name : '—' }}</td>
                        <td>
                            @if($invoice)
                                @if(auth()->user()->hasPermission('view_billing'))
                                    <a href="{{ route('staff.billing.show', $invoice->id) }}" class="invoice-chip">{{ $invoice->invoice_no }}</a>
                                @else
                                    <span class="invoice-chip">{{ $invoice->invoice_no }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if($invoice)
                                <span class="pay-badge pay-badge--{{ $invoice->statusBadgeClass() }}">{{ $invoice->status }}</span>
                            @else
                                <span class="pay-badge pay-badge--unpaid">No invoice</span>
                            @endif
                        </td>
                        <td>
                            <span class="mc-pill mc-pill--{{ $certificate->status }}">{{ ucfirst($certificate->status) }}</span>
                        </td>
                        <td>
                            @if($certificate->isReleased())
                                {{-- Already released: the button stays, but disabled --}}
                                <button type="button" class="btn-release btn-release--done" disabled
                                        title="Released {{ $certificate->released_at?->format('M d, Y g:i A') }}{{ $certificate->releasedBy ? ' by ' . $certificate->releasedBy->first_name . ' ' . $certificate->releasedBy->last_name : '' }}">
                                    <i class="fa-solid fa-check"></i> Released
                                </button>
                            @elseif($certificate->isIssued())
                                @if(auth()->user()->hasPermission('release_medical_certificate'))
                                    <form method="POST"
                                          action="{{ route('staff.medical-certificates.release', $certificate->id) }}"
                                          onsubmit="return confirm('Release this certificate to the patient? Make sure the Doctor has signed the printed copy.');">
                                        @csrf
                                        <button type="submit" class="btn-release" {{ $certificate->canBeReleased() ? '' : 'disabled' }}
                                                title="{{ $certificate->canBeReleased() ? '' : 'The invoice must be fully paid first.' }}">
                                            Release
                                        </button>
                                    </form>
                                    @unless($certificate->canBeReleased())
                                        <div class="mc-note">Awaiting payment</div>
                                    @endunless
                                @else
                                    —
                                @endif
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty-row">No medical certificates found.</td>
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
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-left"></i>
                </span>
            @else
                <a class="page-btn" href="{{ $certificates->previousPageUrl() }}">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
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
                <a class="page-btn" href="{{ $certificates->nextPageUrl() }}">
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
@endsection