@extends('layouts.patient')

@section('head')
<link rel="stylesheet" href="{{ asset('css/patient-medical-report.css') }}">
@endsection

@section('content')

<div class="container">

    <!-- ================= HEADER ================= -->
    <div class="page-header">
        <h2>Medical Report</h2>
    </div>

    <!-- ================= TABLE ================= -->
    <div class="table-wrapper">

        <table class="medical-table">

            <thead>
                <tr>
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
                        <td>{{ $appointment->appointment_date }}</td>

                        <td>{{ $appointment->appointment_time }}</td>

                        <td>
                            Dr. {{ $appointment->doctor->first_name ?? '' }}
                            {{ $appointment->doctor->last_name ?? '' }}
                        </td>

                        <td>
                            <span class="status {{ strtolower($appointment->status) }}">
                                {{ $appointment->status }}
                            </span>
                        </td>

                        <td>
                            <a href="{{ route('patient.medical-report.show', $appointment->id) }}"
                               class="btn-view">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="no-data">
                            No medical records found.
                        </td>
                    </tr>
                @endforelse

            </tbody>

        </table>

    </div>

</div>

@endsection