@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-billing.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endsection

@section('content')
<div class="container">

    <div class="page-header">
        <h1 class="page-title">Billing &amp; Payments</h1>
    </div>

    <!-- ================= STATS ================= -->
    <div class="ab-stats-grid">
        <div class="ab-stat-card ab-stat-card--purple">
            <div class="ab-stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
            <div>
                <p>Total Collected</p>
                <h3>₱{{ number_format($totalCollected, 2) }}</h3>
            </div>
        </div>
        <div class="ab-stat-card ab-stat-card--red">
            <div class="ab-stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
            <div>
                <p>Outstanding Balance</p>
                <h3>₱{{ number_format($totalOutstanding, 2) }}</h3>
            </div>
        </div>
        <div class="ab-stat-card ab-stat-card--green">
            <div class="ab-stat-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
            <div>
                <p>Cash Collected</p>
                <h3>₱{{ number_format($cashCollected, 2) }}</h3>
            </div>
        </div>
        <div class="ab-stat-card ab-stat-card--blue">
            <div class="ab-stat-icon"><i class="fa-solid fa-mobile-screen-button"></i></div>
            <div>
                <p>GCash Collected</p>
                <h3>₱{{ number_format($gcashCollected, 2) }}</h3>
            </div>
        </div>
    </div>

    <div class="ab-counts-row">
        <span class="ab-count-chip ab-count-chip--unpaid">Unpaid: {{ $counts['Unpaid'] }}</span>
        <span class="ab-count-chip ab-count-chip--partial">Partially Paid: {{ $counts['Partially Paid'] }}</span>
        <span class="ab-count-chip ab-count-chip--paid">Paid: {{ $counts['Paid'] }}</span>
        <span class="ab-count-chip ab-count-chip--cancelled">Cancelled: {{ $counts['Cancelled'] }}</span>
    </div>

    <!-- ================= FILTERS ================= -->
    <form method="GET" action="{{ route('admin.billing.index') }}" class="billing-filters">
        <div class="billing-filter-field">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search" placeholder="Search patient or invoice no."
                   value="{{ request('search') }}">
        </div>
        <select name="status" class="billing-filter-select" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            @foreach(['Unpaid','Partially Paid','Paid','Cancelled'] as $s)
                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-filter-apply">Search</button>
        @if(request('search') || request('status'))
            <a href="{{ route('admin.billing.index') }}" class="btn-filter-clear">Clear</a>
        @endif
    </form>

    <!-- ================= TABLE ================= -->
    <div class="table-container">
        <table class="patients-table">
            <thead>
                <tr>
                    <th>Invoice No.</th>
                    <th>Patient</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->invoice_no }}</td>
                    <td>{{ $invoice->patientName() }}</td>
                    <td>₱{{ number_format($invoice->total_amount, 2) }}</td>
                    <td>₱{{ number_format($invoice->amount_paid, 2) }}</td>
                    <td>₱{{ number_format($invoice->balance, 2) }}</td>
                    <td><span class="status-badge status-badge--{{ $invoice->statusBadgeClass() }}">{{ $invoice->status }}</span></td>
                    <td>{{ $invoice->created_at->format('M d, Y') }}</td>
                    <td><a href="{{ route('admin.billing.show', $invoice->id) }}" class="btn-view">View</a></td>
                </tr>
                @empty
                <tr><td colspan="8" class="empty-row">No invoices found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrapper">
        <div class="pagination-info">
            @if($invoices->total() > 0)
                Showing <strong>{{ $invoices->firstItem() }}–{{ $invoices->lastItem() }}</strong>
                of <strong>{{ $invoices->total() }}</strong> result{{ $invoices->total() !== 1 ? 's' : '' }}
            @else
                No results found
            @endif
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
                    return $p === 1 || $p === $lastPage || abs($p - $currentPage) <= 2;
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
                    <a class="page-btn" href="{{ $invoices->appends(request()->query())->url($page) }}">{{ $page }}</a>
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

</div>
@endsection
