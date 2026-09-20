@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-patient.css') }}">
@endsection

@section('content')
<div class="container">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h1 class="page-title">Patient List</h1>
        <p class="page-subtitle">Manage registered and walk-in patients</p>
    </div>

    <!-- ================= ALERTS ================= -->
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <!-- ================= SEARCH & FILTERS ================= -->
    <form method="GET" action="{{ route('doctor.patient') }}" class="filters-bar">

        <div class="filter-field filter-field-search">
            <i class="fa-solid fa-magnifying-glass filter-search-icon"></i>
            <input
                type="text"
                name="search"
                value="{{ $search }}"
                placeholder="Search by patient name, ID, or contact number..."
                class="filter-input filter-search-input"
            >
        </div>

        <div class="filter-field">
            <select name="type" class="filter-input filter-select">
                <option value="all" @selected($typeFilter === 'all')>All Patients</option>
                <option value="registered" @selected($typeFilter === 'registered')>Registered</option>
                <option value="walkin" @selected($typeFilter === 'walkin')>Walk-in</option>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn-filter-apply">
                <i class="fa-solid fa-filter"></i>
                Filter
            </button>
            @if($search !== '' || $typeFilter !== 'all')
                <a href="{{ route('doctor.patient') }}" class="btn-filter-clear">
                    Clear Filters
                </a>
            @endif
        </div>

    </form>

    <!-- ================= TABLE ================= -->
    <div class="table-container">

        <table class="patient-table">

            <thead>
                <tr>
                    <th>Patient ID</th>
                    <th>Patient Name</th>
                    <th>Contact Number</th>
                    <th>Patient Type</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                @forelse($patients as $patient)
                    <tr>
                        <td class="patient-id-cell">{{ $patient['patient_id'] }}</td>
                        <td>{{ $patient['first_name'] }} {{ $patient['last_name'] }}</td>
                        <td>{{ $patient['contact_number'] ?? 'N/A' }}</td>
                        <td>
                            @if($patient['is_walk_in'])
                                <span class="patient-type-badge walkin">Walk-in</span>
                            @else
                                <span class="patient-type-badge registered">Registered</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('doctor.patient.records', $patient['id']) }}" class="btn-view">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="no-data">
                            @if($search !== '' || $typeFilter !== 'all')
                                No patients match your search or filter.
                            @else
                                No patients found.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>

        </table>

    </div>

    <!-- ================= PAGINATION ================= -->
    <div class="pagination-wrapper">
        <div class="pagination-info">
            @if($patients->total() > 0)
                Showing <strong>{{ $patients->firstItem() }}–{{ $patients->lastItem() }}</strong>
                of <strong>{{ $patients->total() }}</strong> result{{ $patients->total() !== 1 ? 's' : '' }}
            @else
                No results found
            @endif
        </div>

        <nav class="pagination-nav" aria-label="Pagination">

            {{-- Previous --}}
            @if($patients->onFirstPage())
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-left"></i>
                </span>
            @else
                <a class="page-btn" href="{{ $patients->previousPageUrl() }}">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            @endif

            {{-- Page Numbers --}}
            @php
                $currentPage = $patients->currentPage();
                $lastPage    = $patients->lastPage();

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
                    <a class="page-btn" href="{{ $patients->url($page) }}">{{ $page }}</a>
                @endif

                @php $prev = $page; @endphp
            @endforeach

            {{-- Next --}}
            @if($patients->hasMorePages())
                <a class="page-btn" href="{{ $patients->nextPageUrl() }}">
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