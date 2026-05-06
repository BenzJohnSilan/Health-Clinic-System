@extends('layouts.doctor')

@section('head')
<style>
/* ================= PAGE HEADER ================= */
.page-header {
    margin-bottom: 20px;
}

.page-title {
    font-size: 28px;
    font-weight: 600;
    color: #333;
}

/* ================= TABLE ================= */
.table-container {
    overflow-x: auto;
}

.patient-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}

.patient-table th,
.patient-table td {
    padding: 12px 15px;
    text-align: left;
    font-size: 14px;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
}

.patient-table th {
    background-color: #f3f4f6;
    font-weight: 600;
}

.patient-table tr:nth-child(even) {
    background-color: #f9fafb;
}

.patient-table tr:hover {
    background-color: #f0f4ff;
}

/* ================= ACTION BUTTON ================= */
.btn-view {
    background-color: #6a0dad;
    color: #fff;
    padding: 7px 12px;
    border-radius: 8px;
    font-size: 13px;
    text-decoration: none;
    display: inline-block;
    transition: 0.2s;
}

.btn-view:hover {
    background-color: #560aaf;
}

/* ================= RESPONSIVE ================= */
@media screen and (max-width: 700px) {
    .patient-table th,
    .patient-table td {
        padding: 8px;
        font-size: 13px;
    }
}
</style>
@endsection

@section('content')

<div class="page-header">
    <h2 class="page-title">Patient List</h2>
</div>

<div class="table-container">

    <table class="patient-table">

        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Contact Number</th>
                <th>Address</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
            @forelse($patients as $patient)
                <tr>
                    <td>
                        {{ $patient->first_name }} {{ $patient->last_name }}
                    </td>

                    <td>
                        {{ $patient->email }}
                    </td>

                    <td>
                        {{ $patient->contact_number ?? 'N/A' }}
                    </td>

                    <td>
                        {{ $patient->address ?? 'N/A' }}
                    </td>

                    <td>
                        <!-- VIEW BUTTON -->
                        <a href="{{ route('doctor.patient.records', $patient->id) }}" class="btn-view">
                            View
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center; padding:20px;">
                        No patients found.
                    </td>
                </tr>
            @endforelse
        </tbody>

    </table>

</div>

@endsection