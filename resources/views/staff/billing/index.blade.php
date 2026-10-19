@extends('layouts.staff')

@section('head')
<link rel="stylesheet" href="{{ asset('css/staff-billing.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-billing.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* ===== Invoices table — same look as the Patient List table ===== */
    .table-container .patients-table {
        border-radius: 12px 12px 0 0;
        box-shadow: 0 3px 12px rgba(0, 0, 0, 0.07);
    }

    .patients-table thead { background: #6a0dad; }

    .patients-table th {
        background: #6a0dad;
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        text-transform: none;
        letter-spacing: 0;
        padding: 13px 16px;
        white-space: nowrap;
        border-bottom: 1px solid #f0e6ff;
    }

    .patients-table td {
        padding: 13px 16px;
        font-size: 14px;
        color: #1f2937;
        border-bottom: 1px solid #f0e6ff;
        vertical-align: middle;
    }

    .patients-table tbody tr:nth-child(even) td { background-color: #f9f5ff; }
    .patients-table tbody tr:nth-child(odd) td  { background-color: #ffffff; }

    .patients-table tbody tr:hover td {
        background-color: #ede9f7;
        transition: background-color 0.2s ease;
    }

    .patients-table tbody tr:last-child td { border-bottom: none; }

    .empty-row {
        text-align: center !important;
        font-style: italic;
        color: #9ca3af;
        padding: 32px 0 !important;
        background-color: #fff !important;
    }

    /* Action buttons — same size for every row: solid = needs action,
       outlined = just view. */
    .patients-table .btn-view {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        box-sizing: border-box;
        width: 148px;
        height: 36px;
        padding: 0 14px;
        border: 1.5px solid #6a0dad;
        border-radius: 8px;
        background-color: #6a0dad;
        color: #fff;
        font-size: 13px;
        font-weight: 600;
        line-height: 1;
        white-space: nowrap;
        text-decoration: none;
        transition: background-color 0.2s ease, color 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease;
    }

    .patients-table .btn-view:hover {
        background-color: #560aaf;
        border-color: #560aaf;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(106, 13, 173, 0.3);
    }

    .patients-table .btn-view--ghost {
        background-color: #fff;
        color: #6a0dad;
    }

    .patients-table .btn-view--ghost:hover {
        background-color: #f3e8ff;
        border-color: #6a0dad;
        color: #560aaf;
        box-shadow: none;
    }

    /* ===== Pagination — attached to the table, like the Patient List ===== */
    .pagination-wrapper {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 0;
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

    @media screen and (max-width: 900px) {
        .patients-table th,
        .patients-table td { padding: 10px 12px; font-size: 13px; }
    }

    @media screen and (max-width: 600px) {
        .pagination-wrapper { flex-direction: column; align-items: center; }
    }
</style>
@endsection

@section('content')
<div class="container">

    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <h1 class="page-title">Billing &amp; Payments</h1>
        @if(auth()->user()->hasPermission('view_billing_reports'))
            <a href="{{ route('staff.billing.report') }}" class="btn-filter-apply" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa-solid fa-chart-column"></i> Revenue Report
            </a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error"><ul><li>{{ session('error') }}</li></ul></div>
    @endif

    <!-- ================= FILTERS ================= -->
    <form method="GET" action="{{ route('staff.billing.index') }}" class="billing-filters">
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
            <a href="{{ route('staff.billing.index') }}" class="btn-filter-clear">Clear</a>
        @endif
    </form>

    <!-- ================= INVOICES TABLE ================= -->
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
                    <td>
                        <span class="status-badge status-badge--{{ $invoice->statusBadgeClass() }}">
                            {{ $invoice->status }}
                        </span>
                    </td>
                    <td>{{ $invoice->created_at->format('M d, Y') }}</td>
                    <td>
                        {{-- Unpaid / Partially Paid -> Collect Payment (opens the invoice
                             straight at its existing payment form). Paid / Cancelled — or
                             a Staff member without record_payment — -> View. --}}
                        @if(in_array($invoice->status, ['Unpaid', 'Partially Paid'], true) && auth()->user()->hasPermission('record_payment'))
                            <a href="{{ route('staff.billing.show', $invoice->id) }}#paymentForm" class="btn-view">Collect Payment</a>
                        @else
                            <a href="{{ route('staff.billing.show', $invoice->id) }}" class="btn-view btn-view--ghost">View</a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="empty-row">No invoices found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- ================= PAGINATION ================= -->
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