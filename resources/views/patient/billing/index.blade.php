@extends('layouts.patient')

@section('head')
<link rel="stylesheet" href="{{ asset('css/patient-billing.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endsection

@section('content')
<div class="container">

    <div class="page-header">
        <h1 class="page-title">Billing &amp; Payments</h1>
    </div>

    <p class="pb-subtitle">View your consultation invoices and payment history.</p>

    <!-- ================= SUMMARY CARDS ================= -->
    <div class="billing-summary-grid">
        <div class="billing-summary-card billing-summary-card--total">
            <div class="billing-summary-icon"><i class="fa-solid fa-file-invoice"></i></div>
            <div class="billing-summary-body">
                <span class="billing-summary-label">Total Invoices</span>
                <span class="billing-summary-value">{{ $summary['total_invoices'] }}</span>
            </div>
        </div>
        <div class="billing-summary-card billing-summary-card--paid">
            <div class="billing-summary-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div class="billing-summary-body">
                <span class="billing-summary-label">Total Paid</span>
                <span class="billing-summary-value">₱{{ number_format($summary['total_paid'], 2) }}</span>
            </div>
        </div>
        <div class="billing-summary-card billing-summary-card--balance">
            <div class="billing-summary-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
            <div class="billing-summary-body">
                <span class="billing-summary-label">Outstanding Balance</span>
                <span class="billing-summary-value">₱{{ number_format($summary['outstanding'], 2) }}</span>
            </div>
        </div>
    </div>

    <!-- ================= BILLING HISTORY ================= -->
    <div class="billing-history-head">
        <h2 class="billing-history-title">Billing History</h2>
    </div>

    <form method="GET" action="{{ route('patient.billing.index') }}" class="billing-filters">
        <div class="billing-filter-field">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search" placeholder="Search invoice no." value="{{ request('search') }}">
        </div>

        <select name="status" class="billing-filter-select" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            @foreach(['Unpaid', 'Partially Paid', 'Paid', 'Cancelled'] as $s)
                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
            @endforeach
        </select>

        <button type="submit" class="btn-filter-apply">Search</button>

        @if(request('search') || request('status'))
            <a href="{{ route('patient.billing.index') }}" class="btn-filter-clear">Clear</a>
        @endif
    </form>

    <!-- ================= DESKTOP / TABLET TABLE ================= -->
    <div class="table-wrapper">
        <table class="billing-table">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Date</th>
                    <th>Service / Services</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                    @php
                        $items = $invoice->items;
                        $firstItem = $items->first();
                        $extraCount = $items->count() - 1;
                    @endphp
                    <tr>
                        <td><span class="billing-invoice-no">{{ $invoice->invoice_no }}</span></td>
                        <td>
                            @if($invoice->appointment)
                                {{ \Carbon\Carbon::parse($invoice->appointment->appointment_date)->format('F d, Y') }}
                            @else
                                {{ $invoice->created_at->format('F d, Y') }}
                            @endif
                        </td>
                        <td>
                            @if($firstItem)
                                {{ $firstItem->description }}
                                @if($extraCount > 0)
                                    <span class="billing-service-more">+{{ $extraCount }} more</span>
                                @endif
                            @else
                                <span class="billing-empty-text">—</span>
                            @endif
                        </td>
                        <td>₱{{ number_format($invoice->total_amount, 2) }}</td>
                        <td>₱{{ number_format($invoice->amount_paid, 2) }}</td>
                        <td>₱{{ number_format($invoice->balance, 2) }}</td>
                        <td>
                            <span class="status-badge status-badge--{{ $invoice->statusBadgeClass() }}">{{ $invoice->status }}</span>
                        </td>
                        <td>
                            <a href="{{ route('patient.billing.show', $invoice->id) }}" class="btn-view">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty-row">No bills yet. A bill will appear here after your consultation.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- ================= MOBILE CARDS ================= -->
    <div class="pb-list">
        @forelse($invoices as $invoice)
            <div class="pb-card">
                <div class="pb-card__top">
                    <div>
                        <div class="pb-invoice-no">{{ $invoice->invoice_no }}</div>
                        @if($invoice->appointment)
                            <div class="pb-date">{{ \Carbon\Carbon::parse($invoice->appointment->appointment_date)->format('F d, Y') }}</div>
                        @else
                            <div class="pb-date">{{ $invoice->created_at->format('F d, Y') }}</div>
                        @endif
                    </div>
                    <span class="status-badge status-badge--{{ $invoice->statusBadgeClass() }}">{{ $invoice->status }}</span>
                </div>

                @if($invoice->items->isNotEmpty())
                    <div class="pb-items">
                        <span class="pb-items__label">Items</span>
                        @foreach($invoice->items as $item)
                            <div class="pb-items__row">
                                <span>{{ $item->description }}</span>
                                <span>₱{{ number_format($item->amount, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="pb-amounts">
                    <div><span>Total</span><strong>₱{{ number_format($invoice->total_amount, 2) }}</strong></div>
                    <div><span>Paid</span><strong>₱{{ number_format($invoice->amount_paid, 2) }}</strong></div>
                    <div><span>Balance</span><strong>₱{{ number_format($invoice->balance, 2) }}</strong></div>
                </div>

                <a href="{{ route('patient.billing.show', $invoice->id) }}" class="pb-view-btn">
                    View Details <i class="fa-solid fa-chevron-right"></i>
                </a>
            </div>
        @empty
            <div class="pb-empty">
                <i class="fa-solid fa-file-invoice"></i>
                <p>No bills yet. A bill will appear here after your consultation.</p>
            </div>
        @endforelse
    </div>

    <!-- ================= PAGINATION ================= -->
    @if($invoices->total() > 0)
    <div class="pagination-wrapper">
        <div class="pagination-info">
            Showing <strong>{{ $invoices->firstItem() }}–{{ $invoices->lastItem() }}</strong>
            of <strong>{{ $invoices->total() }}</strong> result{{ $invoices->total() !== 1 ? 's' : '' }}
        </div>
        <nav class="pagination-nav" aria-label="Pagination">
            @if($invoices->onFirstPage())
                <span class="page-btn disabled"><i class="fa-solid fa-chevron-left"></i></span>
            @else
                <a class="page-btn" href="{{ $invoices->previousPageUrl() }}"><i class="fa-solid fa-chevron-left"></i></a>
            @endif

            @php
                $currentPage = $invoices->currentPage();
                $lastPage    = $invoices->lastPage();
                $pages = collect(range(1, $lastPage))->filter(function ($p) use ($currentPage, $lastPage) {
                    return $p === 1 || $p === $lastPage || abs($p - $currentPage) <= 1;
                })->values();
                $prev = null;
            @endphp
            @foreach($pages as $page)
                @if($prev !== null && $page - $prev > 1)
                    <span class="page-ellipsis">…</span>
                @endif
                @if($page === $currentPage)
                    <span class="page-btn active">{{ $page }}</span>
                @else
                    <a class="page-btn" href="{{ $invoices->url($page) }}">{{ $page }}</a>
                @endif
                @php $prev = $page; @endphp
            @endforeach

            @if($invoices->hasMorePages())
                <a class="page-btn" href="{{ $invoices->nextPageUrl() }}"><i class="fa-solid fa-chevron-right"></i></a>
            @else
                <span class="page-btn disabled"><i class="fa-solid fa-chevron-right"></i></span>
            @endif
        </nav>
    </div>
    @endif

</div>
@endsection