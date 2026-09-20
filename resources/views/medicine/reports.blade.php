@extends('layouts.' . $routePrefix)

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-medicines.css') }}">
<link rel="stylesheet" href="{{ asset('css/medicine-inventory-actions.css') }}">
<link rel="stylesheet" href="{{ asset('css/medicine-reports.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endsection

@section('content')
<div class="container">

    <a href="{{ route($routePrefix . '.medicines.index') }}" class="back-btn">
        <i class="fa-solid fa-arrow-left"></i> Back to Medicine Inventory
    </a>

    <div class="page-header">
        <h2>Inventory Reports</h2>
    </div>

    <!-- ================= SUMMARY CARDS ================= -->
    <div class="report-cards">
        <div class="report-card">
            <span class="report-card-label">Total Medicines</span>
            <span class="report-card-value">{{ $totalMedicines }}</span>
        </div>
        <div class="report-card card-available">
            <span class="report-card-label">Available</span>
            <span class="report-card-value">{{ $available }}</span>
        </div>
        <div class="report-card card-low">
            <span class="report-card-label">Low Stock</span>
            <span class="report-card-value">{{ $lowStock }}</span>
        </div>
        <div class="report-card card-out">
            <span class="report-card-label">Out of Stock</span>
            <span class="report-card-value">{{ $outOfStock }}</span>
        </div>
        <div class="report-card card-out">
            <span class="report-card-label">Expired</span>
            <span class="report-card-value">{{ $expired }}</span>
        </div>
        <div class="report-card card-low">
            <span class="report-card-label">Expiring Soon (30 days)</span>
            <span class="report-card-value">{{ $expiringSoon }}</span>
        </div>
        <div class="report-card card-value">
            <span class="report-card-label">Inventory Value</span>
            <span class="report-card-value">₱{{ number_format($inventoryValue, 2) }}</span>
        </div>
    </div>

    <!-- ================= STOCK MOVEMENT SUMMARY ================= -->
    <div class="report-section">
        <h3>Stock Movement Summary</h3>
        <div class="table-container">
            <table class="medicine-table">
                <thead>
                    <tr>
                        <th>Movement Type</th>
                        <th># of Movements</th>
                        <th>Total Quantity Moved</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $labels = ['stock_in' => 'Stock In', 'stock_out' => 'Stock Out', 'adjustment' => 'Adjustment'];
                    @endphp
                    @forelse($labels as $key => $label)
                        <tr>
                            <td>{{ $label }}</td>
                            <td>{{ $movementSummary[$key]->movement_count ?? 0 }}</td>
                            <td>{{ $movementSummary[$key]->total_quantity ?? 0 }}</td>
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
        <h3>Recent Stock Activity</h3>
        <div class="table-container">
            <table class="medicine-table">
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>Medicine</th>
                        <th>Action</th>
                        <th>Qty</th>
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
                            <td class="qty-change {{ $typeClass }}">{{ $changeDisplay }}</td>
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
