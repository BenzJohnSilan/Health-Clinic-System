@extends('layouts.staff')

@section('head')
<link rel="stylesheet" href="{{ asset('css/staff-patient.css') }}">
@endsection

@section('content')
<div class="container">

    @php
        // Whether the last failed validation came from the Edit form
        // (View Patient → Edit Patient) rather than the Add Walk-in
        // Patient form. Both forms share the same field names, so this
        // flag decides which modal replays `old()` input and reopens.
        $isEditError = (bool) old('edit_type');

        $activeFilters = collect(['search' => $search, 'type' => $type])
            ->filter(fn ($v) => $v !== '' && $v !== 'all');
    @endphp

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h1 class="page-title">Patient List</h1>
        @if($canAddWalkIn)
        <button class="btn-add" onclick="openModal('addPatientModal')">
            <i class="fa-solid fa-user-plus"></i>
            Add Walk-in Patient
        </button>
        @endif
    </div>

    <!-- ================= ALERTS ================= -->
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any() && !$isEditError)
        <div class="alert-error">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- ================= SEARCH & FILTER ================= -->
    <form method="GET" action="{{ route('staff.patients.index') }}" class="filter-bar">
        <input
            type="text"
            name="search"
            class="filter-input"
            placeholder="Search by name or contact number..."
            value="{{ $search }}">

        <select name="type" class="filter-input">
            <option value="all"        {{ $type === 'all'        ? 'selected' : '' }}>All Patients</option>
            <option value="registered" {{ $type === 'registered' ? 'selected' : '' }}>Registered</option>
            <option value="walkin"     {{ $type === 'walkin'     ? 'selected' : '' }}>Walk-in</option>
        </select>

        <button type="submit" class="btn-filter">
            <i class="fa-solid fa-filter"></i> Filter
        </button>

        @if($activeFilters->isNotEmpty())
            <a href="{{ route('staff.patients.index') }}" class="btn-clear-filter">Clear</a>
        @endif
    </form>

    <!-- ================= PATIENTS TABLE ================= -->
    <div class="table-container">
        <table class="patients-table">
            <thead>
                <tr>
                    <th>Patient Name</th>
                    <th>Contact Number</th>
                    <th>Address</th>
                    <th>Type</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($patients as $patient)
                <tr>
                    <td>{{ $patient['first_name'] }} {{ $patient['last_name'] }}</td>
                    <td>{{ $patient['contact_number'] ?: '—' }}</td>
                    <td>{{ $patient['address'] ?: '—' }}</td>
                    <td>
                        @if($patient['is_walk_in'])
                            <span class="type-badge walkin">Walk-in</span>
                        @else
                            <span class="type-badge registered">Registered</span>
                        @endif
                    </td>
                    <td>
                    <div class="action-cell">
                        <button class="btn-view"
                            data-type="{{ $patient['type'] }}"
                            data-id="{{ $patient['raw_id'] }}"
                            data-fname="{{ $patient['first_name'] }}"
                            data-mname="{{ $patient['middle_name'] }}"
                            data-lname="{{ $patient['last_name'] }}"
                            data-suffix="{{ $patient['suffix'] }}"
                            data-birthdate="{{ $patient['birthdate'] }}"
                            data-age="{{ $patient['age'] }}"
                            data-gender="{{ $patient['gender'] }}"
                            data-civil="{{ $patient['civil_status'] }}"
                            data-address="{{ $patient['address'] }}"
                            data-contact="{{ $patient['contact_number'] }}"
                            data-blood="{{ $patient['blood_type'] ?? '' }}"
                            data-allergies="{{ $patient['allergies'] ?? '' }}"
                            data-emergency-name="{{ $patient['emergency_name'] ?? '' }}"
                            data-emergency-contact="{{ $patient['emergency_contact'] ?? '' }}"
                            data-relationship="{{ $patient['relationship'] ?? '' }}"
                            data-emergency-address="{{ $patient['emergency_address'] ?? '' }}"
                            data-patient-type="{{ $patient['is_walk_in'] ? 'Walk-in' : 'Registered' }}"
                            onclick="openViewModal(this)">
                            View
                        </button>

                        @if($canScheduleAppointment || $canViewAppointments)
                        <div class="action-menu">
                            <button type="button" class="action-menu-toggle" aria-haspopup="true" aria-expanded="false" aria-label="More actions">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>

                            <div class="action-menu-dropdown">
                                @if($canScheduleAppointment)
                                <button type="button" class="action-menu-item"
                                    onclick="openAppointmentModal(
                                        '{{ $patient['raw_id'] }}',
                                        '{{ $patient['type'] }}'
                                    )">
                                    <i class="fa-solid fa-calendar-plus"></i> Schedule Appointment
                                </button>
                                @endif

                                @if($canViewAppointments)
                                <a href="{{ route('staff.patients.appointments', ['type' => $patient['type'], 'id' => $patient['raw_id']]) }}"
                                    class="action-menu-item">
                                    <i class="fa-solid fa-calendar-days"></i> View Appointments
                                </a>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="empty-row">No patients found.</td>
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

<!-- ================= ADD WALK-IN PATIENT MODAL ================= -->
<div id="addPatientModal" class="modal-overlay" style="display:none;">
    <div class="modal-box modal-box--wide">

        <button class="modal-close-btn" onclick="closeModal('addPatientModal')" aria-label="Close">&times;</button>
        <h3 class="modal-title">Add Walk-in Patient</h3>
        <p class="modal-subtitle">Creates a new walk-in patient record in the clinic system.</p>

        @if(session('duplicate_warning') && !$isEditError)
            <div class="modal-warning">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>{{ session('duplicate_warning') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('staff.patients.store') }}">
            @csrf
            <input type="hidden" name="confirm_duplicate" value="{{ session('duplicate_warning') ? '1' : '0' }}">
            <input type="hidden" name="search" value="{{ $search }}">
            <input type="hidden" name="type" value="{{ $type }}">

            <!-- PERSONAL INFORMATION -->
            <div class="section-header">
                <i class="fa-solid fa-user"></i> Personal Information
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">First Name <span class="required">*</span></label>
                    <input class="form-input" type="text" name="first_name"
                           value="{{ $isEditError ? '' : old('first_name') }}" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Middle Name</label>
                    <input class="form-input" type="text" name="middle_name"
                           value="{{ $isEditError ? '' : old('middle_name') }}">
                </div>

                <div class="form-group">
                    <label class="form-label">Last Name <span class="required">*</span></label>
                    <input class="form-input" type="text" name="last_name"
                           value="{{ $isEditError ? '' : old('last_name') }}" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Suffix</label>
                    <input class="form-input" type="text" name="suffix"
                           value="{{ $isEditError ? '' : old('suffix') }}" placeholder="Jr., Sr., III…">
                </div>

                <div class="form-group">
                    <label class="form-label">Birthdate <span class="required">*</span></label>
                    <input class="form-input" type="date"
                           name="birthdate"
                           id="birthdateInput"
                           value="{{ $isEditError ? '' : old('birthdate') }}"
                           min="{{ date('Y-m-d', strtotime('-120 years')) }}"
                           max="{{ date('Y-m-d') }}"
                           required>
                </div>

                <div class="form-group">
                    <label class="form-label">Age</label>
                    <input class="form-input" type="text" id="agePreview" readonly placeholder="Auto-computed">
                </div>

                <div class="form-group">
                    <label class="form-label">Gender <span class="required">*</span></label>
                    <select class="form-input" name="gender" required>
                        <option value="">-- Select Gender --</option>
                        <option value="Male"   {{ !$isEditError && old('gender') === 'Male'   ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ !$isEditError && old('gender') === 'Female' ? 'selected' : '' }}>Female</option>
                        <option value="Other"  {{ !$isEditError && old('gender') === 'Other'  ? 'selected' : '' }}>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Civil Status <span class="required">*</span></label>
                    <select class="form-input" name="civil_status" required>
                        <option value="">-- Select Civil Status --</option>
                        <option value="Single"    {{ !$isEditError && old('civil_status') === 'Single'    ? 'selected' : '' }}>Single</option>
                        <option value="Married"   {{ !$isEditError && old('civil_status') === 'Married'   ? 'selected' : '' }}>Married</option>
                        <option value="Widowed"   {{ !$isEditError && old('civil_status') === 'Widowed'   ? 'selected' : '' }}>Widowed</option>
                        <option value="Separated" {{ !$isEditError && old('civil_status') === 'Separated' ? 'selected' : '' }}>Separated</option>
                    </select>
                </div>

                <div class="form-group span-2">
                    <label class="form-label">Contact Number <span class="required">*</span></label>
                    <input class="form-input" type="text"
                           name="contact_number"
                           value="{{ $isEditError ? '' : old('contact_number') }}"
                           maxlength="11"
                           pattern="09[0-9]{9}"
                           title="Must start with 09 and be exactly 11 digits."
                           oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                           required>
                </div>

                <div class="form-group span-2">
                    <label class="form-label">Complete Address <span class="required">*</span></label>
                    <input class="form-input" type="text" name="address"
                           value="{{ $isEditError ? '' : old('address') }}" required>
                </div>
            </div>

            <!-- MEDICAL INFORMATION -->
            <div class="section-header">
                <i class="fa-solid fa-stethoscope"></i> Medical Information
            </div>
            <div class="form-grid">
                <div class="form-group span-2">
                    <label class="form-label">Blood Type</label>
                    <select class="form-input" name="blood_type">
                        <option value="">-- Select Blood Type --</option>
                        <option value="Unknown" {{ !$isEditError && old('blood_type') === 'Unknown' ? 'selected' : '' }}>Unknown / Not Sure</option>
                        @foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt)
                            <option value="{{ $bt }}" {{ !$isEditError && old('blood_type') === $bt ? 'selected' : '' }}>{{ $bt }}</option>
                        @endforeach
                    </select>
                </div>

                @php
                    $addAllergyStatus = !$isEditError ? old('allergy_status') : null;
                @endphp
                <div class="form-group span-2">
                    <label class="form-label">Allergies <span class="required">*</span></label>
                    <div class="choice-toggle-group" id="addAllergyToggleGroup">
                        <label class="choice-toggle {{ $addAllergyStatus === 'none' ? 'is-active' : '' }}">
                            <input type="radio" name="allergy_status" value="none"
                                   onchange="toggleAllergyDetail('addAllergyDetailWrap', this); syncToggleActive('addAllergyToggleGroup')"
                                   {{ $addAllergyStatus === 'none' ? 'checked' : '' }} required>
                            <span class="choice-toggle-check"><i class="fa-solid fa-check"></i></span>
                            <span class="choice-toggle-text">No Known Allergies</span>
                        </label>
                        <label class="choice-toggle {{ $addAllergyStatus === 'has' ? 'is-active' : '' }}">
                            <input type="radio" name="allergy_status" value="has"
                                   onchange="toggleAllergyDetail('addAllergyDetailWrap', this); syncToggleActive('addAllergyToggleGroup')"
                                   {{ $addAllergyStatus === 'has' ? 'checked' : '' }} required>
                            <span class="choice-toggle-check"><i class="fa-solid fa-check"></i></span>
                            <span class="choice-toggle-text">Has Allergies</span>
                        </label>
                    </div>
                </div>

                <div class="form-group span-2" id="addAllergyDetailWrap"
                     style="{{ $addAllergyStatus === 'has' ? '' : 'display:none;' }}">
                    <label class="form-label">Specify Allergies <span class="required">*</span></label>
                    <textarea class="form-input" name="allergies_detail" rows="2"
                              placeholder="e.g. Penicillin, Peanuts, Dust">{{ $addAllergyStatus === 'has' ? old('allergies_detail') : '' }}</textarea>
                </div>
            </div>

            <!-- EMERGENCY CONTACT -->
            <div class="section-header">
                <i class="fa-solid fa-circle-exclamation"></i> Emergency Contact
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Contact Name <span class="required">*</span></label>
                    <input class="form-input" type="text" name="emergency_name"
                           value="{{ $isEditError ? '' : old('emergency_name') }}" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Contact Number <span class="required">*</span></label>
                    <input class="form-input" type="text"
                           name="emergency_contact"
                           value="{{ $isEditError ? '' : old('emergency_contact') }}"
                           maxlength="11"
                           pattern="09[0-9]{9}"
                           title="Must start with 09 and be exactly 11 digits."
                           oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                           required>
                </div>

                @php
                    $addRelationship = !$isEditError ? old('relationship') : null;
                @endphp
                <div class="form-group">
                    <label class="form-label">Relationship <span class="required">*</span></label>
                    <select class="form-input" name="relationship"
                            onchange="toggleRelationshipOther('addRelationshipOtherWrap', this)" required>
                        <option value="">-- Select Relationship --</option>
                        @foreach($relationships as $rel)
                            <option value="{{ $rel }}" {{ $addRelationship === $rel ? 'selected' : '' }}>{{ $rel }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" id="addRelationshipOtherWrap"
                     style="{{ $addRelationship === 'Other' ? '' : 'display:none;' }}">
                    <label class="form-label">Please Specify Relationship <span class="required">*</span></label>
                    <input class="form-input" type="text" name="relationship_other"
                           value="{{ $addRelationship === 'Other' ? old('relationship_other') : '' }}">
                </div>

                <div class="form-group span-2">
                    <label class="form-label">Emergency Address</label>
                    <input class="form-input" type="text" name="emergency_address"
                           value="{{ $isEditError ? '' : old('emergency_address') }}">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-back" onclick="closeModal('addPatientModal')">Cancel</button>
                <button type="submit" class="btn-confirm">Save Patient</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= VIEW / EDIT PATIENT MODAL ================= -->
<div id="viewModal" class="modal-overlay" style="display:none;">
    <div class="modal-box modal-box--wide">

        <button class="modal-close-btn" onclick="closeModal('viewModal')" aria-label="Close">&times;</button>
        <h3 class="modal-title">Patient Details</h3>

        @if($errors->any() && $isEditError)
            <div class="alert-error">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="patientEditForm" method="POST" action="">
            @csrf
            @method('PUT')
            <input type="hidden" name="edit_type" id="edit_type_field" value="{{ $isEditError ? old('edit_type') : '' }}">
            <input type="hidden" name="edit_id" id="edit_id_field" value="{{ $isEditError ? old('edit_id') : '' }}">
            <input type="hidden" name="search" value="{{ $search }}">
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="page" value="{{ $patients->currentPage() }}">

            <!-- PERSONAL INFORMATION -->
            <div class="section-header section-header--with-action">
                <span><i class="fa-solid fa-user"></i> Personal Information</span>
                @if($canEditPatient)
                <button type="button" class="btn-edit-patient" id="editPatientBtn" onclick="enterEditMode()">
                    <i class="fa-solid fa-pen"></i> Edit Patient
                </button>
                @endif
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">First Name</label>
                    <input class="form-input field-editable" type="text" name="first_name" id="v_fname"
                           value="{{ $isEditError ? old('first_name') : '' }}" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Middle Name</label>
                    <input class="form-input field-editable" type="text" name="middle_name" id="v_mname"
                           value="{{ $isEditError ? old('middle_name') : '' }}" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input class="form-input field-editable" type="text" name="last_name" id="v_lname"
                           value="{{ $isEditError ? old('last_name') : '' }}" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Suffix</label>
                    <input class="form-input field-editable" type="text" name="suffix" id="v_suffix"
                           value="{{ $isEditError ? old('suffix') : '' }}" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Birthdate</label>
                    <input class="form-input field-editable" type="date" name="birthdate" id="v_birthdate"
                           value="{{ $isEditError ? old('birthdate') : '' }}"
                           min="{{ date('Y-m-d', strtotime('-120 years')) }}"
                           max="{{ date('Y-m-d') }}" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Age</label>
                    <input class="form-input" type="text" id="v_age" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select class="form-input field-editable" name="gender" id="v_gender" disabled>
                        <option value="">—</option>
                        <option value="Male"   {{ $isEditError && old('gender') === 'Male'   ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ $isEditError && old('gender') === 'Female' ? 'selected' : '' }}>Female</option>
                        <option value="Other"  {{ $isEditError && old('gender') === 'Other'  ? 'selected' : '' }}>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Civil Status</label>
                    <select class="form-input field-editable" name="civil_status" id="v_civil" disabled>
                        <option value="">—</option>
                        <option value="Single"    {{ $isEditError && old('civil_status') === 'Single'    ? 'selected' : '' }}>Single</option>
                        <option value="Married"   {{ $isEditError && old('civil_status') === 'Married'   ? 'selected' : '' }}>Married</option>
                        <option value="Widowed"   {{ $isEditError && old('civil_status') === 'Widowed'   ? 'selected' : '' }}>Widowed</option>
                        <option value="Separated" {{ $isEditError && old('civil_status') === 'Separated' ? 'selected' : '' }}>Separated</option>
                    </select>
                </div>
                <div class="form-group span-2">
                    <label class="form-label">Contact Number</label>
                    <input class="form-input field-editable" type="text" name="contact_number" id="v_contact"
                           value="{{ $isEditError ? old('contact_number') : '' }}"
                           maxlength="11" pattern="09[0-9]{9}"
                           oninput="this.value=this.value.replace(/[^0-9]/g,'')" readonly>
                </div>
                <div class="form-group span-2">
                    <label class="form-label">Complete Address</label>
                    <input class="form-input field-editable" type="text" name="address" id="v_address"
                           value="{{ $isEditError ? old('address') : '' }}" readonly>
                </div>
            </div>

            <!-- MEDICAL INFORMATION -->
            <div class="section-header">
                <i class="fa-solid fa-stethoscope"></i> Medical Information
            </div>
            <div class="form-grid">
                <div class="form-group span-2">
                    <label class="form-label">Blood Type</label>
                    <select class="form-input field-editable" name="blood_type" id="v_blood" disabled>
                        <option value="">-- Select Blood Type --</option>
                        <option value="Unknown" {{ $isEditError && old('blood_type') === 'Unknown' ? 'selected' : '' }}>Unknown / Not Sure</option>
                        @foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt)
                            <option value="{{ $bt }}" {{ $isEditError && old('blood_type') === $bt ? 'selected' : '' }}>{{ $bt }}</option>
                        @endforeach
                    </select>
                </div>

                @php
                    $editAllergyStatus = $isEditError ? old('allergy_status') : null;
                @endphp
                <div class="form-group span-2">
                    <label class="form-label">Allergies</label>
                    <div class="choice-toggle-group" id="editAllergyToggleGroup">
                        <label class="choice-toggle field-editable-toggle {{ $editAllergyStatus === 'none' ? 'is-active' : '' }}" id="editAllergyNoneLabel">
                            <input type="radio" name="allergy_status" value="none" id="v_allergy_none" disabled
                                   onchange="toggleAllergyDetail('editAllergyDetailWrap', this); syncToggleActive('editAllergyToggleGroup')"
                                   {{ $editAllergyStatus === 'none' ? 'checked' : '' }}>
                            <span class="choice-toggle-check"><i class="fa-solid fa-check"></i></span>
                            <span class="choice-toggle-text">No Known Allergies</span>
                        </label>
                        <label class="choice-toggle field-editable-toggle {{ $editAllergyStatus === 'has' ? 'is-active' : '' }}" id="editAllergyHasLabel">
                            <input type="radio" name="allergy_status" value="has" id="v_allergy_has" disabled
                                   onchange="toggleAllergyDetail('editAllergyDetailWrap', this); syncToggleActive('editAllergyToggleGroup')"
                                   {{ $editAllergyStatus === 'has' ? 'checked' : '' }}>
                            <span class="choice-toggle-check"><i class="fa-solid fa-check"></i></span>
                            <span class="choice-toggle-text">Has Allergies</span>
                        </label>
                    </div>
                </div>

                <div class="form-group span-2" id="editAllergyDetailWrap"
                     style="{{ $editAllergyStatus === 'has' ? '' : 'display:none;' }}">
                    <label class="form-label">Specify Allergies</label>
                    <textarea class="form-input field-editable" name="allergies_detail" id="v_allergies_detail" rows="2" readonly
                              placeholder="e.g. Penicillin, Peanuts, Dust">{{ $editAllergyStatus === 'has' ? old('allergies_detail') : '' }}</textarea>
                </div>
            </div>

            <!-- EMERGENCY CONTACT -->
            <div class="section-header">
                <i class="fa-solid fa-circle-exclamation"></i> Emergency Contact
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Contact Name</label>
                    <input class="form-input field-editable" type="text" name="emergency_name" id="v_emergency_name"
                           value="{{ $isEditError ? old('emergency_name') : '' }}" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input class="form-input field-editable" type="text" name="emergency_contact" id="v_emergency_contact"
                           value="{{ $isEditError ? old('emergency_contact') : '' }}"
                           maxlength="11" pattern="09[0-9]{9}"
                           oninput="this.value=this.value.replace(/[^0-9]/g,'')" readonly>
                </div>

                @php
                    $editRelationship = $isEditError ? old('relationship') : null;
                @endphp
                <div class="form-group">
                    <label class="form-label">Relationship</label>
                    <select class="form-input field-editable" name="relationship" id="v_relationship" disabled
                            onchange="toggleRelationshipOther('editRelationshipOtherWrap', this)">
                        <option value="">-- Select Relationship --</option>
                        @foreach($relationships as $rel)
                            <option value="{{ $rel }}" {{ $editRelationship === $rel ? 'selected' : '' }}>{{ $rel }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" id="editRelationshipOtherWrap"
                     style="{{ $editRelationship === 'Other' ? '' : 'display:none;' }}">
                    <label class="form-label">Please Specify Relationship</label>
                    <input class="form-input field-editable" type="text" name="relationship_other" id="v_relationship_other" readonly
                           value="{{ $editRelationship === 'Other' ? old('relationship_other') : '' }}">
                </div>

                <div class="form-group span-2">
                    <label class="form-label">Emergency Address</label>
                    <input class="form-input field-editable" type="text" name="emergency_address" id="v_emergency_address"
                           value="{{ $isEditError ? old('emergency_address') : '' }}" readonly>
                </div>
            </div>

            <!-- PATIENT TYPE -->
            <div class="section-header">
                <i class="fa-solid fa-id-card"></i> Patient Type
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Patient Type</label>
                    <input class="form-input" type="text" id="v_patient_type" readonly>
                </div>
            </div>

            <div class="modal-actions" id="editModalActions" style="display:none;">
                <button type="button" class="btn-back" onclick="cancelEditMode()">Cancel</button>
                <button type="submit" class="btn-confirm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= APPOINTMENT MODAL ================= -->
<div id="appointmentModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">

        <button class="modal-close-btn" onclick="closeModal('appointmentModal')" aria-label="Close">&times;</button>
        <h3 class="modal-title">Add Appointment</h3>

        <form method="POST" action="{{ route('staff.appointments.store') }}">
            @csrf

            <input type="hidden" name="patient_id"   id="appointment_patient_id">
            <input type="hidden" name="patient_type" id="appointment_patient_type">

            <!-- Date -->
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">Date <span class="required">*</span></label>
                <input class="form-input" type="date"
                       name="appointment_date"
                       id="appt_date"
                       min="{{ date('Y-m-d') }}"
                       required>
            </div>

            <!-- Doctor -->
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">Doctor <span class="required">*</span></label>

                @if($doctors->count() === 1)
                    <input class="form-input"
                           type="text"
                           value="{{ $doctors->first()->first_name }} {{ $doctors->first()->last_name }}"
                           disabled
                           style="background-color:#f3f4f6;cursor:not-allowed;">
                    <input type="hidden" name="doctor_id" id="appt_doctor_id" value="{{ $doctors->first()->id }}">
                @else
                    <select class="form-input" name="doctor_id" id="appt_doctor_id" required>
                        <option value="">-- Select Doctor --</option>
                        @foreach($doctors as $doctor)
                            <option value="{{ $doctor->id }}">
                                Dr. {{ $doctor->first_name }} {{ $doctor->last_name }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            <!-- Time Slot -->
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">Time Slot <span class="required">*</span></label>
                <select class="form-input" name="appointment_time" id="appt_time" required>
                    <option value="">-- Select Time Slot --</option>
                    <option value="09:00">9:00 AM – 9:30 AM</option>
                    <option value="09:30">9:30 AM – 10:00 AM</option>
                    <option value="10:00">10:00 AM – 10:30 AM</option>
                    <option value="10:30">10:30 AM – 11:00 AM</option>
                    <option value="11:00">11:00 AM – 11:30 AM</option>
                    <option value="11:30">11:30 AM – 12:00 PM</option>
                    <option value="12:00" data-lunch="true" disabled>12:00 PM – 1:00 PM (Lunch Break)</option>
                    <option value="13:00">1:00 PM – 1:30 PM</option>
                    <option value="13:30">1:30 PM – 2:00 PM</option>
                    <option value="14:00">2:00 PM – 2:30 PM</option>
                    <option value="14:30">2:30 PM – 3:00 PM</option>
                    <option value="15:00">3:00 PM – 3:30 PM</option>
                    <option value="15:30">3:30 PM – 4:00 PM</option>
                    <option value="16:00">4:00 PM – 4:30 PM</option>
                    <option value="16:30">4:30 PM – 5:00 PM</option>
                </select>
            </div>

            <!-- Reason -->
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">Reason <span class="required">*</span></label>
                <textarea class="form-input" name="reason" rows="3" required></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-back" onclick="closeModal('appointmentModal')">Cancel</button>
                <button type="submit" class="btn-confirm">Save Appointment</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= FONT AWESOME ================= -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- ================= JAVASCRIPT ================= -->
@php
    // Pass booked slots to JS (same structure as patient appointments page)
    $staffBookedSlotsJson = $bookedSlots->map(fn($s) => [
        'doctor_id'        => $s->doctor_id,
        'appointment_date' => \Carbon\Carbon::parse($s->appointment_date)->format('Y-m-d'),
        'appointment_time' => substr($s->appointment_time, 0, 5),
    ])->values()->toArray();

    // Fixed relationship options — used by JS to decide whether a stored
    // relationship value should select "Other" instead.
    $relationshipOptionsJson = json_encode($relationships);

    // Base URL template for the Edit Patient form's dynamic action
    // attribute — {type}/{id} are swapped in client-side.
    $patientUpdateUrlTemplate = route('staff.patients.update', ['type' => '__TYPE__', 'id' => '__ID__']);
@endphp

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ===== BOOKED SLOTS FROM SERVER =====
    const bookedSlots = @json($staffBookedSlotsJson);
    const RELATIONSHIP_OPTIONS = @json($relationshipOptionsJson ? json_decode($relationshipOptionsJson) : []);
    const PATIENT_UPDATE_URL_TEMPLATE = @json($patientUpdateUrlTemplate);

    // ===== MODAL HELPERS =====
    window.openModal = function (id) {
        document.getElementById(id).style.display = 'flex';

        if (id === 'addPatientModal') {
            const birthdateInput = document.getElementById('birthdateInput');
            const agePreview     = document.getElementById('agePreview');

            if (!birthdateInput.hasAttribute('data-listener')) {
                birthdateInput.addEventListener('change', function () {
                    agePreview.value = computeAge(this.value);
                });
                birthdateInput.setAttribute('data-listener', 'true');
            }

            agePreview.value = birthdateInput.value ? computeAge(birthdateInput.value) : '';
        }
    };

    window.closeModal = function (id) {
        document.getElementById(id).style.display = 'none';

        if (id === 'viewModal') {
            cancelEditMode();
        }
    };

    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === this) closeModal(this.id);
        });
    });

    // Auto-open Add modal on a validation error from that form.
    @if($errors->any() && !$isEditError)
        openModal('addPatientModal');
    @endif

    // Auto-reopen the View/Edit modal (already in edit mode) after a
    // validation error from the Edit form — the fields already carry
    // the resubmitted old() values via Blade.
    @if($errors->any() && $isEditError)
        document.getElementById('viewModal').style.display = 'flex';
        document.getElementById('patientEditForm').action =
            PATIENT_UPDATE_URL_TEMPLATE
                .replace('__TYPE__', document.getElementById('edit_type_field').value)
                .replace('__ID__', document.getElementById('edit_id_field').value);
        showEditUI();
        syncToggleActive('editAllergyToggleGroup');
    @endif

    // ===== APPOINTMENT MODAL: TIME SLOT LOGIC =====
    const apptDateInput   = document.getElementById('appt_date');
    const apptDoctorInput = document.getElementById('appt_doctor_id');
    const apptTimeSelect  = document.getElementById('appt_time');

    // Set default date to today when modal opens
    window.openAppointmentModal = function (patientId, patientType) {
        document.getElementById('appointment_patient_id').value   = patientId;
        document.getElementById('appointment_patient_type').value = patientType;

        // Reset form fields
        apptTimeSelect.value = '';
        if (apptDoctorInput && apptDoctorInput.tagName === 'SELECT') {
            apptDoctorInput.value = '';
        }

        // Default date to today
        if (!apptDateInput.value) {
            apptDateInput.value = new Date().toISOString().split('T')[0];
        }

        updateStaffTimeSlots();
        openModal('appointmentModal');
    };

    // Re-run slot update when date or doctor changes
    apptDateInput.addEventListener('change', updateStaffTimeSlots);

    if (apptDoctorInput && apptDoctorInput.tagName === 'SELECT') {
        apptDoctorInput.addEventListener('change', updateStaffTimeSlots);
    }

    function updateStaffTimeSlots() {
        const selectedDate   = apptDateInput.value;
        const selectedDoctor = apptDoctorInput ? apptDoctorInput.value : '';
        const today          = new Date().toISOString().split('T')[0];
        const now            = new Date();
        const currentMinutes = now.getHours() * 60 + now.getMinutes();

        apptTimeSelect.querySelectorAll('option').forEach(function (option) {
            if (!option.value) return; // skip placeholder

            // Save original label
            if (!option.getAttribute('data-base-text')) {
                option.setAttribute('data-base-text', option.textContent);
            }
            const baseText = option.getAttribute('data-base-text');

            // Always disable lunch break
            if (option.dataset.lunch) {
                option.disabled    = true;
                option.textContent = baseText;
                return;
            }

            const [h, m]        = option.value.split(':').map(Number);
            const optionMinutes = h * 60 + m;

            // Disable past times when today is selected
            if (selectedDate === today && optionMinutes <= currentMinutes) {
                option.disabled    = true;
                option.textContent = baseText + ' (Unavailable)';
                return;
            }

            // Disable booked slots for selected doctor + date
            const isBooked = selectedDoctor && selectedDate && bookedSlots.some(function (slot) {
                return String(slot.doctor_id)  === String(selectedDoctor) &&
                       slot.appointment_date   === selectedDate &&
                       slot.appointment_time   === option.value;
            });

            option.disabled    = isBooked;
            option.textContent = isBooked ? baseText + ' – Booked' : baseText;
        });
    }

    // ===== ALLERGY / RELATIONSHIP TOGGLE HELPERS (shared by Add + Edit) =====
    window.toggleAllergyDetail = function (wrapId, radioEl) {
        const wrap = document.getElementById(wrapId);
        if (!wrap) return;
        wrap.style.display = radioEl.value === 'has' ? '' : 'none';
        if (radioEl.value !== 'has') {
            const textarea = wrap.querySelector('textarea');
            if (textarea) textarea.value = '';
        }
    };

    window.toggleRelationshipOther = function (wrapId, selectEl) {
        const wrap = document.getElementById(wrapId);
        if (!wrap) return;
        wrap.style.display = selectEl.value === 'Other' ? '' : 'none';
        if (selectEl.value !== 'Other') {
            const input = wrap.querySelector('input');
            if (input) input.value = '';
        }
    };

    window.syncToggleActive = function (groupId) {
        const group = document.getElementById(groupId);
        if (!group) return;
        group.querySelectorAll('.choice-toggle').forEach(function (label) {
            const input = label.querySelector('input[type=radio]');
            label.classList.toggle('is-active', !!(input && input.checked));
        });
    };

    // ===== VIEW / EDIT PATIENT MODAL =====
    let currentPatientData = null; // last-opened patient's raw dataset, for Cancel to revert to

    window.openViewModal = function (btn) {
        const d = btn.dataset;
        currentPatientData = d;

        fillViewFields(d);
        lockEditableFields();
        showViewUI();

        document.getElementById('viewModal').style.display = 'flex';
    };

    function fillViewFields(d) {
        document.getElementById('v_fname').value     = d.fname     || '';
        document.getElementById('v_mname').value     = d.mname     || '';
        document.getElementById('v_lname').value     = d.lname     || '';
        document.getElementById('v_suffix').value    = d.suffix    || '';
        document.getElementById('v_birthdate').value = d.birthdate || '';
        document.getElementById('v_age').value       = d.age       || '—';
        document.getElementById('v_gender').value    = d.gender    || '';
        document.getElementById('v_civil').value     = d.civil     || '';
        document.getElementById('v_address').value   = d.address   || '';
        document.getElementById('v_contact').value   = d.contact   || '';
        document.getElementById('v_blood').value     = d.blood     || '';

        // Allergies: the DB stores either "No Known Allergies", empty, or
        // the actual allergy detail text — resolve that into the toggle +
        // specify field pairing the form uses.
        const allergies = d.allergies || '';
        const hasAllergies = allergies !== '' && allergies !== 'No Known Allergies';
        document.getElementById('v_allergy_none').checked = !hasAllergies;
        document.getElementById('v_allergy_has').checked  = hasAllergies;
        document.getElementById('v_allergies_detail').value = hasAllergies ? allergies : '';
        document.getElementById('editAllergyDetailWrap').style.display = hasAllergies ? '' : 'none';
        syncToggleActive('editAllergyToggleGroup');

        document.getElementById('v_emergency_name').value    = d.emergencyName    || '';
        document.getElementById('v_emergency_contact').value = d.emergencyContact || '';
        document.getElementById('v_emergency_address').value = d.emergencyAddress || '';

        // Relationship: select the matching fixed option, or fall back to
        // "Other" + specify field for any custom stored value.
        const relationship = d.relationship || '';
        const isFixedOption = RELATIONSHIP_OPTIONS.includes(relationship);
        document.getElementById('v_relationship').value = isFixedOption ? relationship : (relationship ? 'Other' : '');
        document.getElementById('v_relationship_other').value = (!isFixedOption && relationship) ? relationship : '';
        document.getElementById('editRelationshipOtherWrap').style.display = (!isFixedOption && relationship) ? '' : 'none';

        document.getElementById('v_patient_type').value = d.patientType || '—';

        document.getElementById('edit_type_field').value = d.type || '';
        document.getElementById('edit_id_field').value   = d.id   || '';
    }

    const EDITABLE_IDS = [
        'v_fname', 'v_mname', 'v_lname', 'v_suffix', 'v_birthdate', 'v_gender', 'v_civil',
        'v_contact', 'v_address', 'v_blood', 'v_allergy_none', 'v_allergy_has',
        'v_allergies_detail', 'v_emergency_name', 'v_emergency_contact', 'v_relationship',
        'v_relationship_other', 'v_emergency_address',
    ];

    function lockEditableFields() {
        EDITABLE_IDS.forEach(function (id) {
            const el = document.getElementById(id);
            if (!el) return;
            if (el.tagName === 'SELECT') {
                el.disabled = true;
            } else if (el.type === 'radio') {
                el.disabled = true;
            } else {
                el.readOnly = true;
            }
        });
    }

    function unlockEditableFields() {
        EDITABLE_IDS.forEach(function (id) {
            const el = document.getElementById(id);
            if (!el) return;
            if (el.tagName === 'SELECT') {
                el.disabled = false;
            } else if (el.type === 'radio') {
                el.disabled = false;
            } else {
                el.readOnly = false;
            }
        });
    }

    function showViewUI() {
        const editBtn = document.getElementById('editPatientBtn');
        if (editBtn) editBtn.style.display = '';
        document.getElementById('editModalActions').style.display = 'none';
    }

    function showEditUI() {
        const editBtn = document.getElementById('editPatientBtn');
        if (editBtn) editBtn.style.display = 'none';
        document.getElementById('editModalActions').style.display = 'flex';
    }

    window.enterEditMode = function () {
        unlockEditableFields();
        showEditUI();

        const type = document.getElementById('edit_type_field').value;
        const id   = document.getElementById('edit_id_field').value;
        document.getElementById('patientEditForm').action =
            PATIENT_UPDATE_URL_TEMPLATE.replace('__TYPE__', type).replace('__ID__', id);

        // Age auto-recompute while editing the birthdate
        const birthdateInput = document.getElementById('v_birthdate');
        if (!birthdateInput.hasAttribute('data-listener')) {
            birthdateInput.addEventListener('change', function () {
                document.getElementById('v_age').value = computeAge(this.value);
            });
            birthdateInput.setAttribute('data-listener', 'true');
        }
    };

    window.cancelEditMode = function () {
        if (currentPatientData) {
            fillViewFields(currentPatientData);
        }
        lockEditableFields();
        showViewUI();
    };

    // ===== AGE COMPUTATION =====
    window.computeAge = function (dateStr) {
        const birth = new Date(dateStr);
        const today = new Date();
        let age = today.getFullYear() - birth.getFullYear();
        const m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
        return age >= 0 ? age : '';
    };

    // ===== ACTION MENU (three-dot dropdown) =====
    // Same fixed-position approach as the Staff Appointments page: the
    // dropdown is pinned via JS to the toggle button's screen position so it
    // isn't clipped by the table's horizontal-scroll wrapper.
    let openMenu = null;

    function closeActionMenu() {
        if (!openMenu) return;
        openMenu.dropdown.style.display = 'none';
        openMenu.dropdown.style.position = '';
        openMenu.dropdown.style.top = '';
        openMenu.dropdown.style.left = '';
        openMenu.toggle.setAttribute('aria-expanded', 'false');
        openMenu = null;
    }

    document.querySelectorAll('.action-menu-toggle').forEach(function (toggle) {
        const wrapper  = toggle.closest('.action-menu');
        const dropdown = wrapper.querySelector('.action-menu-dropdown');

        toggle.addEventListener('click', function (e) {
            e.stopPropagation();

            const alreadyOpenForThis = openMenu && openMenu.dropdown === dropdown;
            closeActionMenu();
            if (alreadyOpenForThis) return;

            const rect = toggle.getBoundingClientRect();
            const menuWidth = 190;

            dropdown.style.display = 'block';
            dropdown.style.position = 'fixed';
            dropdown.style.minWidth = menuWidth + 'px';

            let left = rect.right - menuWidth;
            if (left < 8) left = 8;
            if (left + menuWidth > window.innerWidth - 8) {
                left = window.innerWidth - menuWidth - 8;
            }

            const estimatedMenuHeight = dropdown.offsetHeight || 90;
            let top = rect.bottom + 6;
            if (top + estimatedMenuHeight > window.innerHeight - 8) {
                top = rect.top - estimatedMenuHeight - 6;
            }

            dropdown.style.top  = top + 'px';
            dropdown.style.left = left + 'px';

            toggle.setAttribute('aria-expanded', 'true');
            openMenu = { dropdown: dropdown, toggle: toggle };
        });
    });

    document.addEventListener('click', closeActionMenu);
    window.addEventListener('scroll', closeActionMenu, true);
    window.addEventListener('resize', closeActionMenu);

});
</script>

@endsection