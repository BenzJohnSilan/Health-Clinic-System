@extends('layouts.' . $routePrefix)

@php
    // This view is shared by Admin / Doctor / Staff. Staff and Admin get the
    // dashboard-style redesign; Doctor keeps its current look.
    $isStaff = in_array($routePrefix, ['staff', 'admin'], true);

    $summaryCards = [
        ['label' => 'Total Medicines',          'value' => $totalMedicines,                       'icon' => 'fa-pills',               'class' => ''],
        ['label' => 'Available',                'value' => $available,                            'icon' => 'fa-circle-check',        'class' => 'card-available'],
        ['label' => 'Low Stock',                'value' => $lowStock,                             'icon' => 'fa-triangle-exclamation','class' => 'card-low'],
        ['label' => 'Out of Stock',             'value' => $outOfStock,                           'icon' => 'fa-box-open',            'class' => 'card-out'],
        ['label' => 'Expired',                  'value' => $expired,                              'icon' => 'fa-calendar-xmark',      'class' => 'card-out'],
        ['label' => 'Expiring Soon (30 days)',  'value' => $expiringSoon,                         'icon' => 'fa-hourglass-half',      'class' => 'card-low'],
        ['label' => 'Inventory Value',          'value' => '₱' . number_format($inventoryValue, 2), 'icon' => 'fa-peso-sign',         'class' => 'card-value'],
    ];

    $movementLabels = ['stock_in' => 'Stock In', 'stock_out' => 'Stock Out', 'adjustment' => 'Adjustment'];
    $movementClasses = ['stock_in' => 'movement-in', 'stock_out' => 'movement-out', 'adjustment' => 'movement-adjustment'];
@endphp

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-medicines.css') }}">
<link rel="stylesheet" href="{{ asset('css/medicine-inventory-actions.css') }}">
<link rel="stylesheet" href="{{ asset('css/medicine-reports.css') }}">
@if($isStaff)
<link rel="stylesheet" href="{{ asset('css/staff-medicine-reports.css') }}">
@endif
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endsection

@section('content')
<div class="container {{ $isStaff ? 'reports-page' : '' }}">

    @if($isStaff)
        {{-- ================= HERO (same banner style as the Dashboard) ================= --}}
        <div class="page-hero">
            <div class="page-hero-group">
                <div class="hero-icon-badge">
                    <i class="fa-solid fa-chart-column"></i>
                </div>
                <div>
                    <h1>Inventory Reports</h1>
                    <p>Stock levels, movements and inventory value at a glance.</p>
                </div>
            </div>

            <a href="{{ route($routePrefix . '.medicines.index') }}" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Medicine Inventory</span>
            </a>
        </div>
    @else
        <a href="{{ route($routePrefix . '.medicines.index') }}" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i> Back to Medicine Inventory
        </a>

        <div class="page-header">
            <h2>Inventory Reports</h2>
        </div>
    @endif

    <!-- ================= SUMMARY CARDS ================= -->
    <div class="report-cards">
        @foreach($summaryCards as $card)
            <div class="report-card {{ $card['class'] }}">
                @if($isStaff)
                    <span class="report-card-icon"><i class="fa-solid {{ $card['icon'] }}"></i></span>
                @endif
                <span class="report-card-label">{{ $card['label'] }}</span>
                <span class="report-card-value">{{ $card['value'] }}</span>
            </div>
        @endforeach
    </div>

    <!-- ================= STOCK MOVEMENT SUMMARY ================= -->
    <div class="report-section">
        @if($isStaff)
            <div class="report-section-head">
                <div class="report-section-icon"><i class="fa-solid fa-right-left"></i></div>
                <div>
                    <h3>Stock Movement Summary</h3>
                    <p class="report-section-sub">Total stock in, stock out and adjustments recorded.</p>
                </div>
            </div>
        @else
            <h3>Stock Movement Summary</h3>
        @endif

        <div class="table-container">
            <table class="medicine-table">
                <thead>
                    <tr>
                        <th>Movement Type</th>
                        <th class="col-num"># of Movements</th>
                        <th class="col-num">Total Quantity Moved</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movementLabels as $key => $label)
                        <tr>
                            <td>
                                @if($isStaff)
                                    <span class="movement-label {{ $movementClasses[$key] }}">{{ $label }}</span>
                                @else
                                    {{ $label }}
                                @endif
                            </td>
                            <td class="col-num">{{ $movementSummary[$key]->movement_count ?? 0 }}</td>
                            <td class="col-num">{{ $movementSummary[$key]->total_quantity ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="no-data">No stock movements recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================= RECENT ACTIVITY ================= -->
    <div class="report-section">
        @if($isStaff)
            <div class="report-section-head">
                <div class="report-section-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <div>
                    <h3>Recent Stock Activity</h3>
                    <p class="report-section-sub">The latest stock changes made by the team.</p>
                </div>
            </div>
        @else
            <h3>Recent Stock Activity</h3>
        @endif

        <div class="table-container">
            <table class="medicine-table">
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>Medicine</th>
                        <th>Action</th>
                        <th class="col-num">Qty</th>
                        <th>Performed By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentMovements as $movement)
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
                            <td>{{ $movement->medicine->medicine_name ?? 'Deleted medicine' }}</td>
                            <td><span class="badge-movement {{ $typeClass }}">{{ $movement->action_label }}</span></td>
                            <td class="qty-change col-num {{ $typeClass }}">{{ $changeDisplay }}</td>
                            <td>{{ $movement->user->full_name ?? 'Unknown' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="no-data">No recent stock activity.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection