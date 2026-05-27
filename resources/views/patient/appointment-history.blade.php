@extends('layouts.patient')

@section('head')
<link rel="stylesheet" href="{{ asset('css/patient-appointment-history.css') }}">
@endsection

@section('content')

<div class="container">

    <!-- ================= HEADER ================= -->
    <div class="page-header">
        <h2>Appointment History</h2>
    </div>

    <!-- ================= TABLE ================= -->
    <div class="table-wrapper">

        <table class="medical-table">

            <thead>
                <tr>
                    <th>Ref. No.</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Doctor</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

                @forelse($appointments as $appointment)
                    <tr>

                        <!-- REFERENCE NUMBER -->
                        <td>
                            <span class="ref-no">
                                {{ $appointment->reference_no ?? '—' }}
                            </span>
                        </td>

                        <td>
                            {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('M d, Y') }}
                        </td>

                        <td>
                            {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}
                        </td>

                        <td>
                            Dr. {{ $appointment->doctor->first_name ?? '' }}
                            {{ $appointment->doctor->last_name ?? '' }}
                        </td>

                        <td>
                            {{-- str_replace converts "No Show" → "no-show" so it matches the CSS class --}}
                            <span class="status {{ str_replace(' ', '-', strtolower($appointment->status)) }}">
                                {{ $appointment->status }}
                            </span>
                        </td>

                        <!-- ACTION -->
                        <td>

                            @if($appointment->status === 'Completed')

                                <div class="action-buttons">

                                    <a href="{{ route('patient.medical-report.show', $appointment->id) }}"
                                    class="btn-view">
                                        Medical Report
                                    </a>

                                    <a href="{{ route('patient.prescription.show', $appointment->id) }}"
                                    class="btn-prescription">
                                        Prescription
                                    </a>

                                    <a href="{{ route('patient.medical-certificate.show', $appointment->id) }}"
                                    class="btn-certificate">
                                        Medical Certificate
                                    </a>

                                </div>

                            @elseif(in_array($appointment->status, ['Rejected', 'Cancelled']))

                                <span class="btn-status-label unavailable">
                                    No Available Documents
                                </span>

                            @else

                                <span class="btn-status-label unavailable">
                                    Not Available
                                </span>

                            @endif

                        </td>

                    </tr>
                @empty

                    <tr>
                        <td colspan="6" class="no-data">
                            No medical records found.
                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

    <!-- ================= PAGINATION ================= -->
    <div class="pagination-wrapper">
        <div class="pagination-info">
            @if($appointments->total() > 0)
                Showing <strong>{{ $appointments->firstItem() }}–{{ $appointments->lastItem() }}</strong>
                of <strong>{{ $appointments->total() }}</strong> result{{ $appointments->total() !== 1 ? 's' : '' }}
            @else
                No results found
            @endif
        </div>

        <nav class="pagination-nav" aria-label="Pagination">

            {{-- Previous --}}
            @if($appointments->onFirstPage())
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-left"></i>
                </span>
            @else
                <a class="page-btn" href="{{ $appointments->previousPageUrl() }}">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            @endif

            {{-- Page Numbers --}}
            @php
                $currentPage = $appointments->currentPage();
                $lastPage    = $appointments->lastPage();

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
                    <a class="page-btn" href="{{ $appointments->url($page) }}">{{ $page }}</a>
                @endif

                @php $prev = $page; @endphp
            @endforeach

            {{-- Next --}}
            @if($appointments->hasMorePages())
                <a class="page-btn" href="{{ $appointments->nextPageUrl() }}">
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