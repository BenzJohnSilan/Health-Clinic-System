@extends('layouts.patient')

@section('head')
<link rel="stylesheet" href="{{ asset('css/patient-account-settings.css') }}">
@endsection

@section('content')

<div class="settings-wrapper">

    {{-- ===== PAGE HEADER ===== --}}
    <div class="settings-header">
        <div class="settings-header-icon">
            <i class='bx bx-cog'></i>
        </div>
        <div>
            <h1>Account Settings</h1>
            <p>Manage your profile information and security preferences</p>
        </div>
    </div>

    {{-- ===== ALERTS ===== --}}
    @if(session('success'))
        <div class="alert alert-success">
            <i class='bx bx-check-circle'></i>
            <span>{{ session('success') }}</span>
            <button class="alert-close" onclick="this.parentElement.remove()"><i class='bx bx-x'></i></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-error">
            <i class='bx bx-error-circle'></i>
            <span>Please fix the errors below before saving.</span>
            <button class="alert-close" onclick="this.parentElement.remove()"><i class='bx bx-x'></i></button>
        </div>
    @endif

    {{-- ===== PROFILE COMPLETION STATUS ===== --}}
    <div class="profile-status-card {{ $profileComplete ? 'is-complete' : 'is-incomplete' }}">
        @if($profileComplete)
            <div class="profile-status-title">
                <i class='bx bx-check-circle'></i>
                <span>Profile Status: <strong>Complete</strong></span>
            </div>
        @else
            <div class="profile-status-title">
                <i class='bx bx-error'></i>
                <span>Profile Status: <strong>Incomplete</strong></span>
            </div>
            <p class="profile-status-desc">
                Please complete the following before you can book an appointment:
            </p>
            <ul class="profile-status-missing">
                @foreach($missingProfileFields as $field)
                    <li>{{ $field }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="settings-container">

        {{-- ===== SIDEBAR ===== --}}
        <div class="settings-sidebar">

            <div class="profile-card">

                {{-- ===== AVATAR (independent form — Choose = click avatar) ===== --}}
                <form action="{{ route('patient.profile.avatar.update') }}"
                      method="POST"
                      enctype="multipart/form-data"
                      id="avatarForm">
                    @csrf
                    @method('PUT')

                    <div class="avatar-wrapper" id="avatarPreviewWrapper" onclick="document.getElementById('avatarInput').click()">
                        @if($patient->avatar)
                            <img src="{{ asset('storage/'.$patient->avatar) }}" alt="Avatar" id="avatarPreview">
                        @else
                            <img src="https://via.placeholder.com/84" alt="Avatar" id="avatarPreview">
                        @endif
                        <div class="avatar-overlay">
                            <i class='bx bx-camera'></i>
                        </div>
                    </div>

                    <input type="file" id="avatarInput" name="avatar" accept="image/jpg,image/jpeg,image/png"
                           style="display:none" onchange="previewAvatar(event)">

                    <div class="profile-card-info">
                        <strong>{{ $patient->first_name }} {{ $patient->last_name }}</strong>
                        <span>{{ $patient->email }}</span>
                        <span class="role-badge">{{ $patient->role }}</span>
                    </div>

                    {{-- ===== ONLY 2 BUTTONS: SAVE & REMOVE ===== --}}
                    <div class="avatar-actions-sidebar">
                        <button type="submit" class="btn-avatar-save" id="saveAvatarBtn" disabled>
                            <i class='bx bx-save'></i> Save
                        </button>

                        @if($patient->avatar)
                            <button type="button" class="btn-avatar-remove" onclick="confirmRemoveAvatar()">
                                <i class='bx bx-trash'></i> Remove
                            </button>
                        @endif
                    </div>

                    @error('avatar')
                        <span class="avatar-field-error"><i class='bx bx-error-circle'></i> {{ $message }}</span>
                    @enderror
                </form>

                @if($patient->avatar)
                <form action="{{ route('patient.profile.avatar.remove') }}"
                      method="POST"
                      id="removeAvatarForm"
                      style="display:none;">
                    @csrf
                    @method('DELETE')
                </form>
                @endif

            </div>{{-- end profile-card --}}

            <nav class="tab-nav">
                <button class="tab-btn active" data-tab="profile">
                    <i class='bx bx-user'></i>
                    <span>Profile Information</span>
                    <i class='bx bx-chevron-right arrow'></i>
                </button>
                <button class="tab-btn" data-tab="password">
                    <i class='bx bx-lock-alt'></i>
                    <span>Change Password</span>
                    <i class='bx bx-chevron-right arrow'></i>
                </button>
            </nav>

        </div>{{-- end settings-sidebar --}}

        {{-- ===== MAIN PANEL ===== --}}
        <div class="settings-panel">

            {{-- ===== TAB: PROFILE (NO AVATAR HERE) ===== --}}
            <div class="tab-content active" id="tab-profile">

                <div class="panel-header">
                    <h2><i class='bx bx-user-circle'></i> Profile Information</h2>
                    <p>Update your personal details and contact information</p>
                </div>

                <form id="patientProfileForm" action="{{ route('patient.profile.update') }}" method="POST" novalidate>
                    @csrf
                    @method('PUT')

                    {{-- ===== PERSONAL DETAILS ===== --}}
                    <div class="form-section">
                        <h3 class="section-label">
                            <i class='bx bx-id-card'></i> Personal Details
                        </h3>
                        <div class="form-grid">

                            <div class="form-group">
                                <label>First Name <span class="required">*</span></label>
                                <input type="text" name="first_name"
                                       value="{{ old('first_name', $patient->first_name) }}"
                                       class="{{ $errors->has('first_name') ? 'is-invalid' : '' }}"
                                       placeholder="Enter first name" required>
                                @error('first_name')<span class="field-error"><i class='bx bx-error-circle'></i> {{ $message }}</span>@enderror
                            </div>

                            <div class="form-group">
                                <label>Middle Name</label>
                                <input type="text" name="middle_name"
                                       value="{{ old('middle_name', $patient->middle_name) }}"
                                       placeholder="Enter middle name (optional)">
                            </div>

                            <div class="form-group">
                                <label>Last Name <span class="required">*</span></label>
                                <input type="text" name="last_name"
                                       value="{{ old('last_name', $patient->last_name) }}"
                                       class="{{ $errors->has('last_name') ? 'is-invalid' : '' }}"
                                       placeholder="Enter last name" required>
                                @error('last_name')<span class="field-error"><i class='bx bx-error-circle'></i> {{ $message }}</span>@enderror
                            </div>

                            <div class="form-group">
                                <label>Suffix</label>
                                <input type="text" name="suffix"
                                       value="{{ old('suffix', $patient->suffix) }}"
                                       placeholder="e.g. Jr., Sr., III">
                            </div>

                            <div class="form-group">
                                <label>Gender <span class="required">*</span></label>
                                <select name="gender"
                                        class="{{ $errors->has('gender') ? 'is-invalid' : '' }}" required>
                                    <option value="">— Select gender —</option>
                                    @foreach(['Male','Female','Other'] as $g)
                                        <option value="{{ $g }}"
                                            {{ old('gender', $patient->gender) == $g ? 'selected' : '' }}>
                                            {{ $g }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('gender')<span class="field-error"><i class='bx bx-error-circle'></i> {{ $message }}</span>@enderror
                            </div>

                            <div class="form-group">
                                <label>Civil Status <span class="required">*</span></label>
                                <select name="civil_status"
                                        class="{{ $errors->has('civil_status') ? 'is-invalid' : '' }}" required>
                                    <option value="">— Select status —</option>
                                    @foreach(['Single','Married','Widowed','Separated'] as $cs)
                                        <option value="{{ $cs }}"
                                            {{ old('civil_status', $patient->civil_status) == $cs ? 'selected' : '' }}>
                                            {{ $cs }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('civil_status')<span class="field-error"><i class='bx bx-error-circle'></i> {{ $message }}</span>@enderror
                            </div>

                        </div>
                    </div>

                    {{-- ===== CONTACT DETAILS ===== --}}
                    <div class="form-section">
                        <h3 class="section-label">
                            <i class='bx bx-phone'></i> Contact Details
                        </h3>
                        <div class="form-grid">

                            <div class="form-group full-width">
                                <label>Address <span class="required">*</span></label>
                                <textarea name="address" rows="2"
                                          class="{{ $errors->has('address') ? 'is-invalid' : '' }}"
                                          placeholder="Enter your complete address" required>{{ old('address', $patient->address) }}</textarea>
                                @error('address')<span class="field-error"><i class='bx bx-error-circle'></i> {{ $message }}</span>@enderror
                            </div>

                            <div class="form-group">
                                <label>Contact Number <span class="required">*</span></label>
                                <input type="text" name="contact_number"
                                       value="{{ old('contact_number', $patient->contact_number) }}"
                                       class="{{ $errors->has('contact_number') ? 'is-invalid' : '' }}"
                                       placeholder="e.g. 09XX-XXX-XXXX" required>
                                @error('contact_number')<span class="field-error"><i class='bx bx-error-circle'></i> {{ $message }}</span>@enderror
                            </div>

                        </div>
                    </div>

                    {{-- ===== MEDICAL INFORMATION ===== --}}
                    <div class="form-section">
                        <h3 class="section-label">
                            <i class='bx bx-heart-circle'></i> Medical Information
                        </h3>
                        <div class="form-grid">

                            <div class="form-group">
                                <label>Blood Type <span class="required">*</span></label>
                                <select name="blood_type"
                                        class="{{ $errors->has('blood_type') ? 'is-invalid' : '' }}" required>
                                    <option value="">— Select blood type —</option>
                                    <option value="Unknown" {{ old('blood_type', $patient->blood_type) == 'Unknown' ? 'selected' : '' }}>Unknown / Not Sure</option>
                                    @foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt)
                                        <option value="{{ $bt }}"
                                            {{ old('blood_type', $patient->blood_type) == $bt ? 'selected' : '' }}>
                                            {{ $bt }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('blood_type')<span class="field-error"><i class='bx bx-error-circle'></i> {{ $message }}</span>@enderror
                                <p class="field-hint">Not sure? You can select "Unknown / Not Sure" — this won't block you from booking.</p>
                            </div>

                            {{-- ===== ALLERGIES (FIXED SYMMETRICAL TOGGLE) ===== --}}
                            <div class="form-group full-width">
                                @php
                                    $currentAllergies = old('allergies_detail', ($patient->allergies && $patient->allergies !== 'No Known Allergies') ? $patient->allergies : '');
                                    $currentStatus = old('allergy_status', ($patient->allergies && $patient->allergies !== 'No Known Allergies') ? 'has' : ($patient->allergies ? 'none' : ''));
                                @endphp
                                <label>Allergies <span class="required">*</span></label>

                                <div class="allergy-toggle-group" id="allergyToggleGroup">
                                    <label class="allergy-toggle {{ $currentStatus == 'none' ? 'is-active' : '' }}" id="allergyNoneLabel">
                                        <input type="radio" name="allergy_status" value="none" id="allergyNone"
                                               {{ $currentStatus == 'none' ? 'checked' : '' }} required>
                                        <span class="allergy-toggle-check"><i class='bx bx-check'></i></span>
                                        <span class="allergy-toggle-text">
                                            <strong>No Known Allergies</strong>
                                            <small>I have no allergies to report</small>
                                        </span>
                                    </label>

                                    <label class="allergy-toggle {{ $currentStatus == 'has' ? 'is-active' : '' }}" id="allergyHasLabel">
                                        <input type="radio" name="allergy_status" value="has" id="allergyHas"
                                               {{ $currentStatus == 'has' ? 'checked' : '' }} required>
                                        <span class="allergy-toggle-check"><i class='bx bx-check'></i></span>
                                        <span class="allergy-toggle-text">
                                            <strong>Has Allergies</strong>
                                            <small>I have one or more allergies</small>
                                        </span>
                                    </label>
                                </div>
                                @error('allergy_status')<span class="field-error"><i class='bx bx-error-circle'></i> {{ $message }}</span>@enderror

                                <div class="allergy-detail-wrap" id="allergyDetailWrap" style="{{ $currentStatus == 'has' ? '' : 'display:none;' }}">
                                    <label for="allergiesDetail">Please specify</label>
                                    <textarea name="allergies_detail" id="allergiesDetail" rows="2"
                                              class="{{ $errors->has('allergies_detail') ? 'is-invalid' : '' }}"
                                              placeholder="e.g. Penicillin, Peanuts, Dust">{{ $currentAllergies }}</textarea>
                                    @error('allergies_detail')<span class="field-error"><i class='bx bx-error-circle'></i> {{ $message }}</span>@enderror
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- ===== EMERGENCY CONTACT ===== --}}
                    <div class="form-section">
                        <h3 class="section-label">
                            <i class='bx bx-alarm-exclamation'></i> Emergency Contact
                        </h3>
                        <div class="form-grid">

                            <div class="form-group">
                                <label>Contact Name <span class="required">*</span></label>
                                <input type="text" name="emergency_name"
                                       value="{{ old('emergency_name', $patient->emergency_name) }}"
                                       class="{{ $errors->has('emergency_name') ? 'is-invalid' : '' }}"
                                       placeholder="Full name of emergency contact" required>
                                @error('emergency_name')<span class="field-error"><i class='bx bx-error-circle'></i> {{ $message }}</span>@enderror
                            </div>

                            <div class="form-group">
                                <label>Relationship <span class="required">*</span></label>
                                <select name="relationship"
                                        class="{{ $errors->has('relationship') ? 'is-invalid' : '' }}" required>
                                    <option value="">— Select relationship —</option>
                                    @foreach(['Parent','Spouse','Sibling','Child','Relative','Friend','Guardian','Other'] as $rel)
                                        <option value="{{ $rel }}"
                                            {{ old('relationship', $patient->relationship) == $rel ? 'selected' : '' }}>
                                            {{ $rel }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('relationship')<span class="field-error"><i class='bx bx-error-circle'></i> {{ $message }}</span>@enderror
                            </div>

                            <div class="form-group">
                                <label>Contact Number <span class="required">*</span></label>
                                <input type="text" name="emergency_contact_number"
                                       value="{{ old('emergency_contact_number', $patient->emergency_contact_number) }}"
                                       class="{{ $errors->has('emergency_contact_number') ? 'is-invalid' : '' }}"
                                       placeholder="e.g. 09XX-XXX-XXXX" required>
                                @error('emergency_contact_number')<span class="field-error"><i class='bx bx-error-circle'></i> {{ $message }}</span>@enderror
                            </div>

                            <div class="form-group full-width">
                                <label>Address <span class="required">*</span></label>
                                <textarea name="emergency_address" rows="2"
                                          class="{{ $errors->has('emergency_address') ? 'is-invalid' : '' }}"
                                          placeholder="Emergency contact's complete address" required>{{ old('emergency_address', $patient->emergency_address) }}</textarea>
                                @error('emergency_address')<span class="field-error"><i class='bx bx-error-circle'></i> {{ $message }}</span>@enderror
                            </div>

                        </div>
                    </div>

                    {{-- ===== ACCOUNT DETAILS ===== --}}
                    <div class="form-section">
                        <h3 class="section-label">
                            <i class='bx bx-at'></i> Account Details
                        </h3>
                        <div class="form-grid">

                            <div class="form-group">
                                <label>Username <span class="required">*</span></label>
                                <input type="text" name="username"
                                       value="{{ old('username', $patient->username) }}"
                                       class="{{ $errors->has('username') ? 'is-invalid' : '' }}"
                                       placeholder="Enter username" required>
                                @error('username')<span class="field-error"><i class='bx bx-error-circle'></i> {{ $message }}</span>@enderror
                            </div>

                            <div class="form-group">
                                <label>Email Address <span class="required">*</span></label>
                                <input type="email" name="email"
                                       value="{{ old('email', $patient->email) }}"
                                       class="{{ $errors->has('email') ? 'is-invalid' : '' }}"
                                       placeholder="Enter email address" required>
                                @error('email')<span class="field-error"><i class='bx bx-error-circle'></i> {{ $message }}</span>@enderror
                            </div>

                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-save">
                            <i class='bx bx-save'></i> Save Changes
                        </button>
                    </div>

                </form>
            </div>{{-- end tab-profile --}}

            {{-- ===== TAB: PASSWORD ===== --}}
            <div class="tab-content" id="tab-password">

                <div class="panel-header">
                    <h2><i class='bx bx-shield-alt-2'></i> Change Password</h2>
                    <p>Keep your account secure by using a strong, unique password</p>
                </div>

                <form action="{{ route('patient.change-password.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="form-section">
                        <h3 class="section-label">
                            <i class='bx bx-key'></i> Update Password
                        </h3>

                        <div class="form-grid single-col">

                            <div class="form-group">
                                <label>Current Password <span class="required">*</span></label>
                                <div class="input-password">
                                    <input type="password" name="current_password" id="currentPass"
                                           class="{{ $errors->has('current_password') ? 'is-invalid' : '' }}"
                                           placeholder="Enter your current password" required>
                                    <button type="button" class="toggle-pass"
                                            onclick="togglePassword('currentPass', this)"
                                            title="Show/hide password">
                                        <i class='bx bx-hide'></i>
                                    </button>
                                </div>
                                @error('current_password')<span class="field-error"><i class='bx bx-error-circle'></i> {{ $message }}</span>@enderror
                            </div>

                            <div class="form-group">
                                <label>New Password <span class="required">*</span></label>
                                <div class="input-password">
                                    <input type="password" name="password" id="newPass"
                                           class="{{ $errors->has('password') ? 'is-invalid' : '' }}"
                                           placeholder="Create a strong password"
                                           required onkeyup="checkStrength(this.value)">
                                    <button type="button" class="toggle-pass"
                                            onclick="togglePassword('newPass', this)"
                                            title="Show/hide password">
                                        <i class='bx bx-hide'></i>
                                    </button>
                                </div>
                                <div class="strength-bar">
                                    <div class="strength-fill" id="strengthFill"></div>
                                </div>
                                <span class="strength-label" id="strengthLabel"></span>
                                @error('password')<span class="field-error"><i class='bx bx-error-circle'></i> {{ $message }}</span>@enderror
                            </div>

                            <div class="form-group">
                                <label>Confirm New Password <span class="required">*</span></label>
                                <div class="input-password">
                                    <input type="password" name="password_confirmation" id="confirmPass"
                                           placeholder="Repeat your new password" required>
                                    <button type="button" class="toggle-pass"
                                            onclick="togglePassword('confirmPass', this)"
                                            title="Show/hide password">
                                        <i class='bx bx-hide'></i>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="password-tips">
                        <h4><i class='bx bx-info-circle'></i> Password Requirements</h4>
                        <ul>
                            <li id="req-length"><i class='bx bx-x-circle'></i> At least 6 characters</li>
                            <li id="req-upper"><i class='bx bx-x-circle'></i> At least one uppercase letter</li>
                            <li id="req-number"><i class='bx bx-x-circle'></i> At least one number</li>
                        </ul>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-save btn-password">
                            <i class='bx bx-lock-open-alt'></i> Update Password
                        </button>
                    </div>

                </form>
            </div>{{-- end tab-password --}}

        </div>{{-- end settings-panel --}}
    </div>{{-- end settings-container --}}
</div>{{-- end settings-wrapper --}}

<script>
/* ===== TAB SWITCHING ===== */
const tabBtns     = document.querySelectorAll('.tab-btn');
const tabContents = document.querySelectorAll('.tab-content');

@if($errors->has('current_password') || $errors->has('password'))
    document.addEventListener('DOMContentLoaded', () => switchTab('password'));
@endif

tabBtns.forEach(btn => {
    btn.addEventListener('click', () => switchTab(btn.dataset.tab));
});

function switchTab(tabName) {
    tabBtns.forEach(b => b.classList.remove('active'));
    tabContents.forEach(c => c.classList.remove('active'));
    const btn   = document.querySelector(`[data-tab="${tabName}"]`);
    const panel = document.getElementById(`tab-${tabName}`);
    if (btn)   btn.classList.add('active');
    if (panel) panel.classList.add('active');
}

/* ===== ALLERGY STATUS TOGGLE ===== */
(function () {
    const noneRadio  = document.getElementById('allergyNone');
    const hasRadio   = document.getElementById('allergyHas');
    const noneLabel  = document.getElementById('allergyNoneLabel');
    const hasLabel   = document.getElementById('allergyHasLabel');
    const detailWrap = document.getElementById('allergyDetailWrap');
    const detail     = document.getElementById('allergiesDetail');
    if (!noneRadio || !hasRadio || !detailWrap || !detail) return;

    function syncAllergyDetail() {
        const isHas = hasRadio.checked;

        noneLabel.classList.toggle('is-active', !isHas && noneRadio.checked);
        hasLabel.classList.toggle('is-active', isHas);

        if (isHas) {
            detailWrap.style.display = '';
            detail.setAttribute('required', 'required');
        } else {
            detailWrap.style.display = 'none';
            detail.removeAttribute('required');
        }
    }
    noneRadio.addEventListener('change', syncAllergyDetail);
    hasRadio.addEventListener('change', syncAllergyDetail);
    syncAllergyDetail();
})();

/* ===== PROFILE PICTURE — PREVIEW + SAVE BUTTON STATE (sidebar) ===== */
/* ===== PROFILE REQUIRED-FIELD VALIDATION =====
   Report every blank required field in one submit instead of stopping at the
   browser's first native validation message. */
(function () {
    const form = document.getElementById('patientProfileForm');
    if (!form) return;

    function groupFor(field) {
        return field.closest('.form-group');
    }

    function clearClientError(group) {
        if (!group) return;
        group.querySelectorAll('.client-field-error').forEach(error => error.remove());
        group.querySelectorAll('[aria-invalid="true"]').forEach(field => {
            field.removeAttribute('aria-invalid');
            field.classList.remove('is-invalid');
        });
    }

    function showClientError(field) {
        const group = groupFor(field);
        if (!group) return;

        group.querySelectorAll('input, select, textarea').forEach(control => {
            control.classList.add('is-invalid');
            control.setAttribute('aria-invalid', 'true');
        });

        if (!group.querySelector('.client-field-error')) {
            const error = document.createElement('span');
            error.className = 'field-error client-field-error';
            error.innerHTML = "<i class='bx bx-error-circle'></i> This field is required.";
            group.appendChild(error);
        }
    }

    function isEmpty(field) {
        if (field.type === 'radio') {
            return !form.querySelector(`input[name="${field.name}"]:checked`);
        }
        return !field.value.trim();
    }

    form.addEventListener('submit', event => {
        form.querySelectorAll('.client-field-error').forEach(error => error.remove());
        form.querySelectorAll('[required]').forEach(field => {
            field.removeAttribute('aria-invalid');
            field.classList.remove('is-invalid');
        });

        const missing = [];
        const checkedNames = new Set();
        form.querySelectorAll('[required]').forEach(field => {
            if (checkedNames.has(field.name)) return;
            checkedNames.add(field.name);
            if (isEmpty(field)) {
                missing.push(field);
                showClientError(field);
            }
        });

        if (missing.length) {
            event.preventDefault();
            missing[0].focus();
        }
    });

    form.addEventListener('input', event => clearClientError(groupFor(event.target)));
    form.addEventListener('change', event => clearClientError(groupFor(event.target)));
})();

function previewAvatar(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('avatarPreview').src = e.target.result;
    };
    reader.readAsDataURL(file);

    const saveBtn = document.getElementById('saveAvatarBtn');
    if (saveBtn) saveBtn.disabled = false;
}

/* ===== PROFILE PICTURE — REMOVE CONFIRMATION ===== */
function confirmRemoveAvatar() {
    if (confirm('Are you sure you want to remove your profile picture?')) {
        document.getElementById('removeAvatarForm').submit();
    }
}

/* ===== TOGGLE PASSWORD VISIBILITY ===== */
function togglePassword(id, btn) {
    const input = document.getElementById(id);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type     = 'text';
        icon.className = 'bx bx-show';
    } else {
        input.type     = 'password';
        icon.className = 'bx bx-hide';
    }
}

/* ===== PASSWORD STRENGTH ===== */
function checkStrength(val) {
    const fill      = document.getElementById('strengthFill');
    const label     = document.getElementById('strengthLabel');
    const reqLength = document.getElementById('req-length');
    const reqUpper  = document.getElementById('req-upper');
    const reqNumber = document.getElementById('req-number');

    const hasLength = val.length >= 6;
    const hasUpper  = /[A-Z]/.test(val);
    const hasNumber = /[0-9]/.test(val);

    const toggle = (el, ok) => {
        el.querySelector('i').className = ok ? 'bx bx-check-circle' : 'bx bx-x-circle';
        el.classList.toggle('met', ok);
    };
    toggle(reqLength, hasLength);
    toggle(reqUpper,  hasUpper);
    toggle(reqNumber, hasNumber);

    const score  = [hasLength, hasUpper, hasNumber].filter(Boolean).length;
    const levels = ['', 'weak', 'fair', 'strong'];
    const labels = ['', 'Weak', 'Fair', 'Strong'];

    fill.className    = 'strength-fill ' + (levels[score] || '');
    label.textContent = val.length ? labels[score] : '';
    label.className   = 'strength-label ' + (levels[score] || '');
}
</script>

@endsection
