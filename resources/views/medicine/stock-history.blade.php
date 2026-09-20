@extends('layouts.' . $routePrefix)

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-medicines.css') }}">
<link rel="stylesheet" href="{{ asset('css/medicine-inventory-actions.css') }}">
<link rel="stylesheet" href="{{ asset('css/medicine-stock-history.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endsection

@section('content')
<div class="container">

    <a href="{{ route($routePrefix . '.medicines.index') }}" class="back-btn">
        <i class="fa-solid fa-arrow-left"></i> Back to Medicine Inventory
    </a>

    <div class="page-header">
        <div>
            <h2>Stock History — {{ $medicine->medicine_name }}</h2>
            <p class="history-subtitle">
                Current Stock: <strong>{{ $medicine->quantity }} {{ $medicine->unit }}</strong>
                &nbsp;•&nbsp;
                Brand: {{ $medicine->brand }}
                &nbsp;•&nbsp;
                Category: {{ $medicine->category }}
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    <div class="table-container">
        <table class="medicine-table history-table">
            <thead>
                <tr>
                    <th>Date &amp; Time</th>
                    <th>Action</th>
                    <th>Qty</th>
                    <th>Before</th>
                    <th>After</th>
                    <th>Reason</th>
                    <th>Reference / Batch</th>
                    <th>Performed By</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movements as $movement)
                    @php
                        $typeClass = match($movement->movement_type) {
                            'stock_in'   => 'movement-in',
                            'stock_out'  => 'movement-out',
                            default      => 'movement-adjustment',
                        };
                        $changeDisplay = $movement->quantity_change > 0
                            ? '+' . $movement->quantity_change
                            : (string) $movement->quantity_change;
                    @endphp
                    <tr>
                        <td>{{ $movement->created_at->format('M d, Y g:i A') }}</td>
                        <td><span class="badge-movement {{ $typeClass }}">{{ $movement->action_label }}</span></td>
                        <td class="qty-change {{ $typeClass }}">{{ $changeDisplay }}</td>
                        <td>{{ $movement->quantity_before }}</td>
                        <td>{{ $movement->quantity_after }}</td>
                        <td>{{ $movement->reason }}</td>
                        @php
                            // Prefer the linked batch (accurate for every
                            // movement recorded since batch tracking was
                            // added); fall back to the older free-text
                            // batch_no column for pre-batch history rows.
                            $batchLabel = $movement->batch->batch_no ?? $movement->batch_no;
                        @endphp
                        <td>
                            @if($movement->reference_no || $batchLabel)
                                @if($movement->reference_no)<span>Ref: {{ $movement->reference_no }}</span>@endif
                                @if($movement->reference_no && $batchLabel)<br>@endif
                                @if($batchLabel)<span>Batch: {{ $batchLabel }}</span>@endif
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>{{ $movement->user->full_name ?? 'Unknown' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="no-data">No stock movements recorded for this medicine yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($movements->hasPages())
        {{-- Hand-rolled to match the app's existing page-btn styling
             instead of Laravel's default (Tailwind-based) pagination
             view, since Tailwind isn't used anywhere in this project. --}}
        <div class="pagination-wrapper">
            <div class="pagination-info">
                Showing <strong>{{ $movements->firstItem() }}–{{ $movements->lastItem() }}</strong> of <strong>{{ $movements->total() }}</strong> result{{ $movements->total() !== 1 ? 's' : '' }}
            </div>
            <nav class="pagination-nav" aria-label="Pagination">
                @if($movements->onFirstPage())
                    <span class="page-btn disabled"><i class="fa-solid fa-chevron-left"></i></span>
                @else
                    <a href="{{ $movements->previousPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                @endif

                @php $lastPage = $movements->lastPage(); $current = $movements->currentPage(); @endphp
                @for($page = 1; $page <= $lastPage; $page++)
                    @if($page == 1 || $page == $lastPage || abs($page - $current) <= 2)
                        <a href="{{ $movements->url($page) }}" class="page-btn {{ $page == $current ? 'active' : '' }}">{{ $page }}</a>
                    @elseif($page == $current - 3 || $page == $current + 3)
                        <span class="page-ellipsis">…</span>
                    @endif
                @endfor

                @if($movements->hasMorePages())
                    <a href="{{ $movements->nextPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                @else
                    <span class="page-btn disabled"><i class="fa-solid fa-chevron-right"></i></span>
                @endif
            </nav>
        </div>
    @endif

</div>
@endsection
