@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-medical-certificates.css') }}">
@endsection

@section('content')

<div class="container">

    <div class="page-header">
        <h1 class="page-title">Medical Certificates</h1>
        <a href="{{ route('doctor.medical-certificates.direct.index') }}" class="btn-create">
            <i class='bx bx-plus'></i> Create Medical Certificate
        </a>
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <!-- ================= FILTER TABS ================= -->
    <div class="filter-tabs">
        <a href="{{ route('doctor.medical-certificates.index', ['status' => 'all']) }}"
           class="filter-tab {{ $filter === 'all' ? 'active' : '' }}">All</a>
        <a href="{{ route('doctor.medical-certificates.index', ['status' => 'pending']) }}"
           class="filter-tab {{ $filter === 'pending' ? 'active' : '' }}">Pending</a>
        <a href="{{ route('doctor.medical-certificates.index', ['status' => 'issued']) }}"
           class="filter-tab {{ $filter === 'issued' ? 'active' : '' }}">Issued</a>
        <a href="{{ route('doctor.medical-certificates.index', ['status' => 'correction_requested']) }}"
           class="filter-tab {{ $filter === 'correction_requested' ? 'active' : '' }}">Correction Requested</a>
        <a href="{{ route('doctor.medical-certificates.index', ['status' => 'rejected']) }}"
           class="filter-tab {{ $filter === 'rejected' ? 'active' : '' }}">Rejected</a>
    </div>

    <!-- ================= TABLE ================= -->
    <div class="table-container">
        <table class="record-table">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Appointment</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($certificates as $certificate)
                    <tr>
                        <td>
                            {{ $certificate->resolvedPatientName() }}
                        </td>
                        <td>
                            {{ optional($certificate->appointment)->appointment_date
                                ? \Carbon\Carbon::parse($certificate->appointment->appointment_date)->format('M d, Y')
                                : '—' }}
                        </td>
                        <td>{{ $certificate->displayPurpose() }}</td>
                        <td>
                            <span class="status {{ $certificate->status }}">
                                {{ $certificate->isCorrectionRequested() ? 'Correction Requested' : ucfirst($certificate->status) }}
                            </span>
                        </td>
                        <td>
                            @if($certificate->isPending())
                                <a href="{{ route('doctor.medical-certificates.show', $certificate->id) }}" class="btn-review">
                                    Review
                                </a>
                            @elseif($certificate->isCorrectionRequested())
                                <a href="{{ route('doctor.medical-certificates.correction.review', $certificate->id) }}" class="btn-review">
                                    Review Correction
                                </a>
                            @else
                                <a href="{{ route('doctor.medical-certificates.show', $certificate->id) }}" class="btn-view">
                                    View
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="no-data">No medical certificate requests found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:18px;">
        {{ $certificates->onEachSide(1)->links() }}
    </div>

</div>

@endsection
