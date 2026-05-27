@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-medical-records.css') }}">
@endsection

@section('content')

<div class="container">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h1 class="page-title">Medical Records</h1>
    </div>

    <!-- ================= ALERTS ================= -->
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <!-- ================= TABLE ================= -->
    <div class="table-container">

        <table class="record-table">

            <thead>
                <tr>
                    <th>Patient Name</th>
                    <th>Appointment Date</th>
                    <th>Diagnosis</th>
                    <th>Treatment</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                @forelse($records as $record)
                    <tr>
                        <td>
                            {{ $record->appointment->patientName() ?? 'N/A' }}
                        </td>

                        <td>
                            @if(optional($record->appointment)->appointment_date)
                                {{ \Carbon\Carbon::parse($record->appointment->appointment_date)->format('M d, Y') }}
                            @else
                                N/A
                            @endif
                        </td>

                        <td>{{ $record->diagnosis ?? 'N/A' }}</td>

                        <td>{{ $record->treatment ?? 'N/A' }}</td>

                        <td>
                            <a href="{{ route('doctor.medical-records.show', $record->appointment->id) }}" class="btn-view">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="no-data">No medical records found.</td>
                    </tr>
                @endforelse
            </tbody>

        </table>

    </div>

    <!-- ================= PAGINATION ================= -->
    <div class="pagination-wrapper">
        <div class="pagination-info">
            @if($records->total() > 0)
                Showing <strong>{{ $records->firstItem() }}–{{ $records->lastItem() }}</strong>
                of <strong>{{ $records->total() }}</strong> result{{ $records->total() !== 1 ? 's' : '' }}
            @else
                No results found
            @endif
        </div>

        <nav class="pagination-nav" aria-label="Pagination">

            {{-- Previous --}}
            @if($records->onFirstPage())
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-left"></i>
                </span>
            @else
                <a class="page-btn" href="{{ $records->previousPageUrl() }}">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            @endif

            {{-- Page Numbers --}}
            @php
                $currentPage = $records->currentPage();
                $lastPage    = $records->lastPage();

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
                    <a class="page-btn" href="{{ $records->url($page) }}">{{ $page }}</a>
                @endif

                @php $prev = $page; @endphp
            @endforeach

            {{-- Next --}}
            @if($records->hasMorePages())
                <a class="page-btn" href="{{ $records->nextPageUrl() }}">
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