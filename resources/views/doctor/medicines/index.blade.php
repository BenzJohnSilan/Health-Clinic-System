@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-medicines.css') }}">
@endsection

@section('content')

<div class="container">

    <!-- HEADER -->
    <div class="page-header">
        <h2>Medicine Inventory</h2>
    </div>

    <!-- SUCCESS MESSAGE -->
    @if(session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <!-- TABLE -->
    <div class="table-container">
        <table class="medicine-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Medicine Name</th>
                    <th>Brand</th>
                    <th>Category</th>
                    <th>Dosage</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Price</th>
                    <th>Total Value</th>
                    <th>Expiration Date</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                @forelse($medicines as $index => $medicine)
                <tr>
                    <td>{{ $index + 1 }}</td>

                    <td>{{ $medicine->medicine_name }}</td>
                    <td>{{ $medicine->brand }}</td>
                    <td>{{ $medicine->category }}</td>
                    <td>{{ $medicine->dosage }}</td>
                    <td>{{ $medicine->quantity }}</td>
                    <td>{{ $medicine->unit }}</td>

                    <td>₱{{ number_format($medicine->price, 2) }}</td>

                    {{-- TOTAL VALUE --}}
                    <td>
                        ₱{{ number_format($medicine->price * $medicine->quantity, 2) }}
                    </td>

                    <td>
                        {{ \Carbon\Carbon::parse($medicine->expiration_date)->format('M d, Y') }}
                    </td>

                    <td>
                        @if($medicine->is_expired)
                            <span class="badge out-of-stock">Expired</span>
                        @elseif($medicine->status === 'Available')
                            <span class="badge available">Available</span>
                        @elseif($medicine->status === 'Low Stock')
                            <span class="badge low-stock">Low Stock</span>
                        @else
                            <span class="badge out-of-stock">Out of Stock</span>
                        @endif
                    </td>

                </tr>
                @empty
                <tr>
                    <td colspan="12" style="text-align:center; padding:20px; color:#888;">
                        No medicines in inventory yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

@endsection