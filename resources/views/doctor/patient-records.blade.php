@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-patient-records.css') }}">
@endsection

@section('content')

<div class="container">

    {{-- ================= HEADER ================= --}}
    <div class="page-header">

        <h1 class="page-title">Patient Records</h1>

        <a href="{{ route('doctor.patient') }}" class="back-btn">
            ← Back
        </a>

    </div>

    {{-- ================= PATIENT INFO ================= --}}
    <div class="card">

        <h2 class="card-title">Patient Information</h2>

        <div class="info-grid">

            <div class="info-item">
                <div class="info-label">Full Name</div>
                <div class="info-value">
                    {{ $patient['first_name'] }} {{ $patient['last_name'] }}
                </div>
            </div>

            <div class="info-item">
                <div class="info-label">Age</div>
                <div class="info-value">{{ $patient['age'] ?? 'N/A' }}</div>
            </div>

            <div class="info-item">
                <div class="info-label">Gender</div>
                <div class="info-value">{{ $patient['gender'] ?? 'N/A' }}</div>
            </div>

            <div class="info-item">
                <div class="info-label">Contact Number</div>
                <div class="info-value">{{ $patient['contact_number'] ?? 'N/A' }}</div>
            </div>

            <div class="info-item">
                <div class="info-label">Address</div>
                <div class="info-value">{{ $patient['address'] ?? 'N/A' }}</div>
            </div>

            <div class="info-item">
                <div class="info-label">Patient Type</div>
                <div class="info-value">
                    {{ $patient['is_walk_in'] ? 'Walk-in' : 'Registered' }}
                </div>
            </div>

        </div>

    </div>

    {{-- ================= APPOINTMENT HISTORY ================= --}}
    <div class="card">

        <h2 class="card-title">Appointment History</h2>

        <div class="table-container">
            <table class="record-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Reason</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($appointments as $appointment)
                        <tr>
                            <td>{{ $appointment->appointment_date }}</td>
                            <td>{{ $appointment->appointment_time }}</td>
                            <td>
                                <span class="badge {{ strtolower($appointment->status) }}">
                                    {{ $appointment->status }}
                                </span>
                            </td>
                            <td>{{ $appointment->reason }}</td>
                            <td>
                                <a href="{{ route('doctor.medical-records.show', $appointment->id) }}" class="btn-view">
                                    View Medical Record
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-text">No appointment history found.</td>
                        </tr>
                    @endforelse

                </tbody>
            </table>
        </div>

    </div>

</div>

@endsection