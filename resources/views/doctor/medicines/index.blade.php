@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-medicines.css') }}">
@endsection

@section('content')

<div class="container">

    <!-- ================= HEADER ================= -->
    <div class="page-header">
        <h2>Medicine Inventory</h2>
    </div>

    <!-- ================= SUCCESS MESSAGE ================= -->
    @if(session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <!-- ================= TABLE ================= -->
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
                    <td>{{ $medicines->firstItem() + $index }}</td>

                    <td>{{ $medicine->medicine_name }}</td>
                    <td>{{ $medicine->brand }}</td>
                    <td>{{ $medicine->category }}</td>
                    <td>{{ $medicine->dosage }}</td>
                    <td>{{ $medicine->quantity }}</td>
                    <td>{{ $medicine->unit }}</td>

                    <td>₱{{ number_format($medicine->price, 2) }}</td>

                    <td>₱{{ number_format($medicine->price * $medicine->quantity, 2) }}</td>

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
                    <td colspan="11" class="no-data">
                        No medicines in inventory yet.
                    </td>
                </tr>
                @endforelse
            </tbody>

        </table>
    </div>

    <!-- ================= PAGINATION ================= -->
    <div class="pagination-wrapper">
        <div class="pagination-info">
            @if($medicines->total() > 0)
                Showing <strong>{{ $medicines->firstItem() }}–{{ $medicines->lastItem() }}</strong>
                of <strong>{{ $medicines->total() }}</strong> result{{ $medicines->total() !== 1 ? 's' : '' }}
            @else
                No results found
            @endif
        </div>

        <nav class="pagination-nav" aria-label="Pagination">

            {{-- Previous --}}
            @if($medicines->onFirstPage())
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-left"></i>
                </span>
            @else
                <a class="page-btn" href="{{ $medicines->previousPageUrl() }}">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            @endif

            {{-- Page Numbers --}}
            @php
                $currentPage = $medicines->currentPage();
                $lastPage    = $medicines->lastPage();

                $pages = collect(range(1, $lastPage))->filter(function ($p) use ($currentPage, $lastPage) {
                    return $p === 1
                        || $p === $lastPage
                        || abs($p - $currentPage) <= 2;
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
                    <a class="page-btn" href="{{ $medicines->url($page) }}">{{ $page }}</a>
                @endif

                @php $prev = $page; @endphp
            @endforeach

            {{-- Next --}}
            @if($medicines->hasMorePages())
                <a class="page-btn" href="{{ $medicines->nextPageUrl() }}">
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

<!-- ================= FONT AWESOME ================= -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

@endsection