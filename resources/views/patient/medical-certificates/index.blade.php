@extends('layouts.patient')

@section('head')
<link rel="stylesheet" href="{{ asset('css/patient-medical-certificates.css') }}">
@endsection

@section('content')

<div class="container">

    <!-- ================= HEADER ================= -->
    <div class="page-header">
        <h2>Medical Certificates</h2>
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <!-- ================= TABLE ================= -->
    <div class="table-wrapper">
        <table class="medical-table">
            <thead>
                <tr>
                    <th>Certificate No.</th>
                    <th>Appointment</th>
                    <th>Purpose</th>
                    <th>Released On</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($certificates as $certificate)
                    <tr>
                        <td><span class="cert-no">{{ $certificate->certificate_number }}</span></td>
                        <td>
                            {{ optional($certificate->appointment)->appointment_date
                                ? \Carbon\Carbon::parse($certificate->appointment->appointment_date)->format('M d, Y')
                                : '—' }}
                        </td>
                        <td>{{ $certificate->displayPurpose() }}</td>
                        <td>{{ $certificate->released_at?->format('M d, Y') ?? '—' }}</td>
                        <td>
                            <div class="action-buttons">
                                <a href="{{ route('patient.medical-certificates.show', $certificate->id) }}" class="btn-view">View</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="no-data">
                            No released medical certificates yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- ================= PAGINATION ================= -->
    <div class="pagination-wrapper">
        <div class="pagination-info">
            @if($certificates->total() > 0)
                Showing <strong>{{ $certificates->firstItem() }}–{{ $certificates->lastItem() }}</strong>
                of <strong>{{ $certificates->total() }}</strong> result{{ $certificates->total() !== 1 ? 's' : '' }}
            @else
                No results found
            @endif
        </div>

        <nav class="pagination-nav" aria-label="Pagination">

            {{-- Previous --}}
            @if($certificates->onFirstPage())
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-left"></i>
                </span>
            @else
                <a class="page-btn" href="{{ $certificates->previousPageUrl() }}">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            @endif

            {{-- Page Numbers --}}
            @php
                $currentPage = $certificates->currentPage();
                $lastPage    = $certificates->lastPage();

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
                    <a class="page-btn" href="{{ $certificates->url($page) }}">{{ $page }}</a>
                @endif

                @php $prev = $page; @endphp
            @endforeach

            {{-- Next --}}
            @if($certificates->hasMorePages())
                <a class="page-btn" href="{{ $certificates->nextPageUrl() }}">
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

@endsection