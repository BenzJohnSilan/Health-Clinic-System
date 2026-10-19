<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — ClinicRMS</title>
{{-- marks the page as "JS is on" so the wizard styles apply (no flash) --}}
<script>document.documentElement.classList.add('js');</script>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
{{-- DATE PICKER (flatpickr) --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" integrity="sha384-RkASv+6KfBMW9eknReJIJ6b3UnjKOKC5bOUaNgIY778NFbQ8MtWq9Lr/khUgqtTt" crossorigin="anonymous">
<link rel="stylesheet" href="{{ asset('css/register.css') }}">
<link rel="stylesheet" href="{{ asset('css/auth-extras.css') }}">
<link rel="stylesheet" href="{{ asset('css/register-datepicker.css') }}">

</head>

<body>

<div class="bg-blob bg-blob-1"></div>
<div class="bg-blob bg-blob-2"></div>

<div class="wrapper">

    <!-- ===== LEFT ===== -->
    <div class="left">
        <div class="left-inner">
            <div class="left-icon"><i class='bx bx-plus-medical'></i></div>

            <h1>Health Clinic Record<br>Management System</h1>
            <div class="left-divider"></div>

            <p>Create your account to access<br>clinic services and appointments.</p>

            <p style="margin-bottom:14px;">Already have an account?</p>
            <a href="{{ route('login') }}" class="btn-login">
                <i class='bx bx-log-in'></i> Login Here
            </a>
        </div>
    </div>

    <!-- ===== RIGHT ===== -->
    <div class="right">

        <div class="right-header">
            <h2>Create Account ✨</h2>
            <p>Fill out the form below to register as a patient.</p>
        </div>

        <!-- MESSAGES -->
        @php($fieldErrors = collect($errors->keys())->reject(fn ($key) => $key === 'registration'))
        <div class="messages">
            @if($errors->has('registration'))
                <div class="alert-error" role="alert" data-registration-error><i class='bx bx-error-circle'></i> {{ $errors->first('registration') }}</div>
            @endif
            @if($fieldErrors->isNotEmpty())
                <div class="alert-error" role="alert"><i class='bx bx-error-circle'></i> Please check the highlighted fields below.</div>
            @endif
            @if(session('error'))
                <div class="alert-error" role="alert"><i class='bx bx-error-circle'></i> {{ session('error') }}</div>
            @endif
            @if(session('success'))
                <div class="alert-success"><i class='bx bx-check-circle'></i> {{ session('success') }}</div>
            @endif
        </div>

        {{-- data-prevent-double-submit stops the double-click 419 error --}}
        <form method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data"
              id="registerForm" data-prevent-double-submit>
            @csrf

            <!-- ===== STEPPER (visible only when JS is on) ===== -->
            <ol class="wizard-stepper" id="wizardStepper" aria-label="Registration steps">
                <li class="is-current">
                    <button type="button" class="wizard-step-btn" data-goto="1" aria-current="step">
                        <span class="wizard-dot">1</span>
                        <span class="wizard-label">Personal</span>
                    </button>
                </li>
                <li>
                    <button type="button" class="wizard-step-btn" data-goto="2" disabled>
                        <span class="wizard-dot">2</span>
                        <span class="wizard-label">Valid ID</span>
                    </button>
                </li>
                <li>
                    <button type="button" class="wizard-step-btn" data-goto="3" disabled>
                        <span class="wizard-dot">3</span>
                        <span class="wizard-label">Account</span>
                    </button>
                </li>
            </ol>

            <!-- =====================================================
                 STEP 1 — PERSONAL INFORMATION
                 ===================================================== -->
            <div class="wizard-step is-active" data-step="1" data-title="Personal details" tabindex="-1">

                <div class="section-header">
                    <div class="section-header-icon"><i class='bx bxs-user-detail'></i></div>
                    <span>Personal Information</span>
                </div>

                <div class="form-row">
                    <div class="input-group">
                        <label class="input-label" for="first_name">First Name *</label>
                        <div class="input-wrap">
                            <i class='bx bxs-user input-icon'></i>
                            <input type="text" id="first_name" name="first_name" placeholder="Juan"
                                   value="{{ old('first_name') }}" maxlength="50" autocomplete="given-name"
                                   @class(['is-invalid' => $errors->has('first_name')])
                                   @error('first_name') aria-invalid="true" aria-describedby="first_name-error" @enderror
                                   required>
                        </div>
                        <x-auth.field-error name="first_name" />
                    </div>
                    <div class="input-group">
                        <label class="input-label" for="middle_name">Middle Name</label>
                        <div class="input-wrap">
                            <i class='bx bxs-user input-icon'></i>
                            <input type="text" id="middle_name" name="middle_name" placeholder="Santos"
                                   value="{{ old('middle_name') }}" maxlength="50" autocomplete="additional-name"
                                   @class(['is-invalid' => $errors->has('middle_name')])
                                   @error('middle_name') aria-invalid="true" aria-describedby="middle_name-error" @enderror>
                        </div>
                        <x-auth.field-error name="middle_name" />
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-group">
                        <label class="input-label" for="last_name">Last Name *</label>
                        <div class="input-wrap">
                            <i class='bx bxs-user input-icon'></i>
                            <input type="text" id="last_name" name="last_name" placeholder="dela Cruz"
                                   value="{{ old('last_name') }}" maxlength="50" autocomplete="family-name"
                                   @class(['is-invalid' => $errors->has('last_name')])
                                   @error('last_name') aria-invalid="true" aria-describedby="last_name-error" @enderror
                                   required>
                        </div>
                        <x-auth.field-error name="last_name" />
                    </div>
                    <div class="input-group">
                        <label class="input-label" for="suffix">Suffix</label>
                        <div class="input-wrap">
                            <i class='bx bxs-tag input-icon'></i>
                            <input type="text" id="suffix" name="suffix" placeholder="Jr., Sr., III"
                                   value="{{ old('suffix') }}" maxlength="10" autocomplete="honorific-suffix"
                                   @class(['is-invalid' => $errors->has('suffix')])
                                   @error('suffix') aria-invalid="true" aria-describedby="suffix-error" @enderror>
                        </div>
                        <x-auth.field-error name="suffix" />
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-group">
                        <label class="input-label" for="birthdate">Birthdate *</label>
                        <div class="input-wrap">
                            <i class='bx bxs-calendar input-icon'></i>
                            {{-- max = the date that makes the person exactly 18 today (server rule: 18 years old or above) --}}
                            <input type="date" id="birthdate" name="birthdate" value="{{ old('birthdate') }}"
                                   min="1900-01-02" max="{{ now()->subYearsNoOverflow(18)->toDateString() }}" autocomplete="bday"
                                   @class(['is-invalid' => $errors->has('birthdate')])
                                   @error('birthdate') aria-invalid="true" aria-describedby="birthdate-error" @enderror
                                   required>
                        </div>
                        <x-auth.field-error name="birthdate" />
                    </div>
                    <div class="input-group">
                        <label class="input-label" for="agePreview">Age</label>
                        <div class="input-wrap">
                            <i class='bx bxs-hourglass input-icon'></i>
                            <input type="text" id="agePreview" placeholder="Auto-filled" readonly
                                   style="background: var(--card-bg); cursor: not-allowed;">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-group">
                        <label class="input-label" for="gender">Gender *</label>
                        <div class="input-wrap">
                            <i class='bx bx-male-female input-icon'></i>
                            <select id="gender" name="gender" autocomplete="sex"
                                    @class(['is-invalid' => $errors->has('gender')])
                                    @error('gender') aria-invalid="true" aria-describedby="gender-error" @enderror
                                    required>
                                <option value="">-- Select Gender --</option>
                                <option value="Male"   {{ old('gender')=='Male'   ? 'selected':'' }}>Male</option>
                                <option value="Female" {{ old('gender')=='Female' ? 'selected':'' }}>Female</option>
                                <option value="Other"  {{ old('gender')=='Other'  ? 'selected':'' }}>Other</option>
                            </select>
                        </div>
                        <x-auth.field-error name="gender" />
                    </div>
                    <div class="input-group">
                        <label class="input-label" for="civil_status">Civil Status *</label>
                        <div class="input-wrap">
                            <i class='bx bxs-heart input-icon'></i>
                            <select id="civil_status" name="civil_status"
                                    @class(['is-invalid' => $errors->has('civil_status')])
                                    @error('civil_status') aria-invalid="true" aria-describedby="civil_status-error" @enderror
                                    required>
                                <option value="">-- Select Status --</option>
                                <option value="Single"    {{ old('civil_status')=='Single'    ? 'selected':'' }}>Single</option>
                                <option value="Married"   {{ old('civil_status')=='Married'   ? 'selected':'' }}>Married</option>
                                <option value="Widowed"   {{ old('civil_status')=='Widowed'   ? 'selected':'' }}>Widowed</option>
                                <option value="Separated" {{ old('civil_status')=='Separated' ? 'selected':'' }}>Separated</option>
                            </select>
                        </div>
                        <x-auth.field-error name="civil_status" />
                    </div>
                </div>

                <div class="input-group">
                    <label class="input-label" for="address">Complete Address *</label>
                    <div class="input-wrap">
                        <i class='bx bxs-map input-icon'></i>
                        <input type="text" id="address" name="address"
                               placeholder="House No., Street, Barangay, City, Province"
                               value="{{ old('address') }}" maxlength="100" autocomplete="street-address"
                               @class(['is-invalid' => $errors->has('address')])
                               @error('address') aria-invalid="true" aria-describedby="address-error" @enderror
                               required>
                    </div>
                    <x-auth.field-error name="address" />
                </div>

                <div class="input-group">
                    <label class="input-label" for="contact_number">Contact Number *</label>
                    <div class="input-wrap">
                        <i class='bx bxs-phone input-icon'></i>
                        <input type="tel" id="contact_number" name="contact_number"
                               placeholder="09XXXXXXXXX" maxlength="11" inputmode="numeric"
                               pattern="09[0-9]{9}"
                               title="Must start with 09 and be exactly 11 digits (e.g., 09123456789)"
                               value="{{ old('contact_number') }}" autocomplete="tel"
                               @class(['is-invalid' => $errors->has('contact_number')])
                               @error('contact_number') aria-invalid="true" aria-describedby="contact_number-error" @enderror
                               required>
                    </div>
                    <x-auth.field-error name="contact_number" />
                </div>

            </div>

            <!-- =====================================================
                 STEP 2 — VERIFICATION (VALID ID)
                 ===================================================== -->
            <div class="wizard-step" data-step="2" data-title="Valid ID" tabindex="-1">

                <div class="section-header">
                    <div class="section-header-icon"><i class='bx bxs-id-card'></i></div>
                    <span>Verification Information</span>
                </div>

                <div class="input-group">
                    <label class="input-label" for="id_type">ID Type *</label>
                    <div class="input-wrap">
                        <i class='bx bxs-credit-card input-icon'></i>
                        <select id="id_type" name="id_type"
                                @class(['is-invalid' => $errors->has('id_type')])
                                @error('id_type') aria-invalid="true" aria-describedby="id_type-error" @enderror
                                required>
                            <option value="">-- Select ID Type --</option>
                            <option value="PhilHealth"        {{ old('id_type')=='PhilHealth'        ? 'selected':'' }}>PhilHealth</option>
                            <option value="Driver's License"  {{ old('id_type')=="Driver's License"  ? 'selected':'' }}>Driver's License</option>
                            <option value="Passport"          {{ old('id_type')=='Passport'          ? 'selected':'' }}>Passport</option>
                            <option value="SSS"               {{ old('id_type')=='SSS'               ? 'selected':'' }}>SSS</option>
                            <option value="UMID"              {{ old('id_type')=='UMID'              ? 'selected':'' }}>UMID</option>
                            <option value="Voter's ID"        {{ old('id_type')=="Voter's ID"        ? 'selected':'' }}>Voter's ID</option>
                            <option value="Others"            {{ old('id_type')=="Others"            ? 'selected':'' }}>Others</option>
                        </select>
                    </div>
                    <x-auth.field-error name="id_type" />
                </div>

                <div class="input-group">
                    <label class="input-label" for="valid_id">Upload Valid ID *</label>
                    @if(session('temp_valid_id'))
                        {{-- Show preview of previously uploaded valid ID --}}
                        <div class="file-preview">
                            <img src="{{ route('register.valid-id-preview') }}" alt="Valid ID Preview" class="file-preview-img">
                            <div class="file-preview-info">
                                <i class='bx bx-check-circle' style="color:#22c55e; font-size:16px;"></i>
                                <span>Valid ID uploaded</span>
                            </div>
                            <label class="file-reupload-btn">
                                <i class='bx bx-refresh'></i> Change ID
                                <input type="file" id="valid_id" name="valid_id" accept="image/jpg,image/jpeg,image/png" style="display:none;"
                                       onchange="previewFile(this, null, 'idLabel')">
                            </label>
                        </div>
                    @else
                        <label @class(['file-label', 'is-invalid' => $errors->has('valid_id')])>
                            <i class='bx bx-upload'></i>
                            <span id="idLabel" data-default="Click to upload your valid ID (JPG, PNG, max 2 MB)">Click to upload your valid ID (JPG, PNG, max 2 MB)</span>
                            <input type="file" id="valid_id" name="valid_id" accept="image/jpg,image/jpeg,image/png" required
                                   onchange="previewFile(this, null, 'idLabel')">
                        </label>
                    @endif
                    {{-- instant check (wrong type / too big / missing), before the form is even sent --}}
                    <div class="field-error" id="validIdClientError" role="alert" hidden></div>
                    <x-auth.field-error name="valid_id" />
                </div>

                {{-- Profile Picture and Reason for Registration are no longer
                     collected during registration. Profile Picture can be added
                     later by the patient via the Patient Profile page (Account
                     Settings). Medical Information and Emergency Contact are
                     also completed there before booking their first appointment. --}}

            </div>

            <!-- =====================================================
                 STEP 3 — LOGIN INFORMATION + CONSENT
                 ===================================================== -->
            <div class="wizard-step" data-step="3" data-title="Account" tabindex="-1">

                <div class="section-header">
                    <div class="section-header-icon"><i class='bx bxs-lock-alt'></i></div>
                    <span>Login Information</span>
                </div>

                <div class="form-row">
                    <div class="input-group">
                        <label class="input-label" for="username">Username *</label>
                        <div class="input-wrap">
                            <i class='bx bxs-user-circle input-icon'></i>
                            <input type="text" id="username" name="username" placeholder="Choose a username"
                                   value="{{ old('username') }}" minlength="3" maxlength="50"
                                   pattern="[A-Za-z0-9._\-]+"
                                   title="Letters, numbers, dots, dashes and underscores only (min. 3 characters)"
                                   autocomplete="username" autocapitalize="none" spellcheck="false"
                                   @class(['is-invalid' => $errors->has('username')])
                                   @error('username') aria-invalid="true" aria-describedby="username-error" @enderror
                                   required>
                        </div>
                        <x-auth.field-error name="username" />
                    </div>
                    <div class="input-group">
                        <label class="input-label" for="email">Email Address *</label>
                        <div class="input-wrap">
                            <i class='bx bxs-envelope input-icon'></i>
                            <input type="email" id="email" name="email" placeholder="your@email.com"
                                   value="{{ old('email') }}" maxlength="255" autocomplete="email"
                                   @class(['is-invalid' => $errors->has('email')])
                                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                                   required>
                        </div>
                        <x-auth.field-error name="email" />
                    </div>
                </div>

                <!-- PASSWORD WITH STRENGTH CHECKER -->
                <div class="input-group">
                    <label class="input-label" for="password">Password *</label>
                    <div class="input-wrap">
                        <i class='bx bxs-lock-alt input-icon'></i>
                        <input type="password" id="password" name="password" placeholder="Min. 8 characters"
                               autocomplete="new-password"
                               @class(['is-invalid' => $errors->has('password')])
                               @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                               required>
                        <button type="button" class="input-toggle" id="togglePassword"
                                aria-label="Show password" aria-pressed="false">
                            <i class='bx bxs-show'></i>
                        </button>
                    </div>
                    <x-auth.field-error name="password" />

                    <!-- ✅ PASSWORD STRENGTH INDICATOR -->
                    <div class="password-strength" id="passwordStrength" style="display:none;">
                        <div class="strength-bar">
                            <span id="bar1"></span>
                            <span id="bar2"></span>
                            <span id="bar3"></span>
                            <span id="bar4"></span>
                        </div>
                        <div class="strength-label" id="strengthLabel">Enter a password</div>
                        <div class="password-checklist">
                            <div class="check-item" id="check-length">
                                <i class='bx bx-x-circle'></i> At least 8 characters
                            </div>
                            <div class="check-item" id="check-upper">
                                <i class='bx bx-x-circle'></i> Uppercase letter (A-Z)
                            </div>
                            <div class="check-item" id="check-lower">
                                <i class='bx bx-x-circle'></i> Lowercase letter (a-z)
                            </div>
                            <div class="check-item" id="check-number">
                                <i class='bx bx-x-circle'></i> Number (0-9)
                            </div>
                            <div class="check-item" id="check-special">
                                <i class='bx bx-x-circle'></i> Special character (@$!%*#?&)
                            </div>
                        </div>
                    </div>
                </div>

                <div class="input-group">
                    <label class="input-label" for="password_confirmation">Confirm Password *</label>
                    <div class="input-wrap">
                        <i class='bx bxs-lock input-icon'></i>
                        <input type="password" name="password_confirmation" id="password_confirmation"
                               placeholder="Re-enter password" autocomplete="new-password" required>
                        <button type="button" class="input-toggle" id="toggleConfirm"
                                aria-label="Show password" aria-pressed="false">
                            <i class='bx bxs-show'></i>
                        </button>
                    </div>
                    <div class="field-hint" id="matchHint" aria-live="polite"></div>
                </div>

                <!-- ===== TERMS + PRIVACY CONSENT ===== -->
                <div class="consent-group">
                    <label class="consent-label">
                        <input type="checkbox" id="consent" name="consent" value="1"
                               {{ old('consent') ? 'checked' : '' }}
                               @error('consent') aria-invalid="true" aria-describedby="consent-error" @enderror
                               required>
                        <span>
                            I accept the
                            <a href="{{ route('terms') }}" class="link-btn" target="_blank" rel="noopener">Terms and Conditions</a>
                            and
                            <a href="{{ route('privacy') }}" class="link-btn" target="_blank" rel="noopener">Privacy Policy</a>.
                        </span>
                    </label>
                    <x-auth.field-error name="consent" />
                </div>

            </div>

            <!-- Submit: with JS on, only shown on the last step -->
            <button type="submit" class="register-btn" data-wizard-submit data-loading-text="Creating account...">
                <i class='bx bx-user-plus'></i> Create Account
            </button>

            <!-- Back / Next (visible only when JS is on) -->
            <div class="wizard-nav" id="wizardNav">
                <button type="button" class="wizard-btn wizard-btn-back" id="wizardBack" hidden>
                    <i class='bx bx-left-arrow-alt'></i> Back
                </button>
                <button type="button" class="wizard-btn wizard-btn-next" id="wizardNext">
                    Next <i class='bx bx-right-arrow-alt'></i>
                </button>
            </div>

            <!-- announces "Step 2 of 3" to screen readers -->
            <div class="sr-only" id="wizardLive" aria-live="polite"></div>
        </form>

    </div>
</div>


<script src="{{ asset('js/prevent-double-submit.js') }}"></script>
{{-- DATE PICKER (flatpickr): pinned version + integrity hash; if it cannot load, the native date input is used --}}
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js" integrity="sha384-5JqMv4L/Xa0hfvtF06qboNdhvuYXUku9ZrhZh3bSk8VXF0A/RuSLHpLsSV9Zqhl6" crossorigin="anonymous"></script>
<script>
// ===== FILE PREVIEW + INSTANT ID CHECK =====
const MAX_ID_BYTES      = 2 * 1024 * 1024;             // same as the server: max 2 MB
const ALLOWED_ID_TYPES  = ['image/jpeg', 'image/png']; // same as the server: JPG / PNG
const idClientError     = document.getElementById('validIdClientError');

function showIdError(message) {
    idClientError.innerHTML = "<div><i class='bx bx-error-circle'></i> <span></span></div>";
    idClientError.querySelector('span').textContent = message;
    idClientError.hidden = false;
}

function previewFile(input, imgId, labelId) {
    const file  = input.files[0];
    const label = labelId ? document.getElementById(labelId) : null;

    input.closest('.file-label')?.classList.remove('is-invalid');
    idClientError.hidden = true;
    idClientError.innerHTML = '';

    if (!file) return;

    let problem = '';
    if (!ALLOWED_ID_TYPES.includes(file.type)) {
        problem = 'The valid ID must be a JPG or PNG image.';
    } else if (file.size > MAX_ID_BYTES) {
        problem = 'The valid ID must not be larger than 2 MB.';
    }

    if (problem) {
        input.value = '';                                   // reject the file
        if (label && label.dataset.default) label.textContent = label.dataset.default;
        showIdError(problem);
        return;
    }

    // Update label text
    if (label) {
        label.textContent = file.name;
    }

    // Update image preview if an img element id was provided
    if (imgId && document.getElementById(imgId)) {
        const reader = new FileReader();
        reader.onload = e => { document.getElementById(imgId).src = e.target.result; };
        reader.readAsDataURL(file);
    }
}

// ===== AGE AUTO-FILL =====
const birthdateInput = document.getElementById('birthdate');
const agePreview     = document.getElementById('agePreview');

// ===== FORM ELEMENT =====
const registrationForm = document.getElementById('registerForm');

function calculateAge(val) {
    if (!val) return;
    const birth = new Date(val);
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
    agePreview.value = age + ' years old';
}

calculateAge(birthdateInput.value);
birthdateInput.addEventListener('change', function () { calculateAge(this.value); });

// ===== BIRTHDATE DATE PICKER (flatpickr, themed in css/register-datepicker.css) =====
// The real field keeps its name and the Y-m-d value the server expects; the visible
// field only shows it nicely ("October 15, 2000"). If flatpickr cannot load, the
// browser's own date input keeps working.
if (window.flatpickr) {
    const bdMin = birthdateInput.min;
    const bdMax = birthdateInput.max;

    const syncYear = fp => {
        if (!fp._yearBtn) return;
        fp._yearBtn.firstChild.textContent = String(fp.currentYear);
        fp._yearList.querySelectorAll('li').forEach(li => {
            const on = li.dataset.year === String(fp.currentYear);
            li.classList.toggle('is-active', on);
            li.setAttribute('aria-selected', on ? 'true' : 'false');
        });
    };

    flatpickr(birthdateInput, {
        altInput: true,
        altInputClass: 'birthdate-display',
        altFormat: 'F j, Y',
        dateFormat: 'Y-m-d',
        minDate: bdMin,
        maxDate: bdMax,
        disableMobile: true,
        allowInput: true,        // keeps the field "required" for the step check (read-only fields skip it)
        monthSelectorType: 'dropdown',
        position: 'auto left',    // under the field, or above it when there is no room (so the page never grows/scrolls)
        onReady(selected, dateStr, fp) {
            const alt = fp.altInput;
            alt.placeholder = 'Select date';
            alt.autocomplete = 'bday';
            // Pick from the calendar: no typing (Backspace/Delete clears the date)
            alt.addEventListener('keydown', e => {
                if (e.key === 'Backspace' || e.key === 'Delete') { e.preventDefault(); fp.clear(); return; }
                if (e.key.length === 1 && !e.ctrlKey && !e.metaKey) e.preventDefault();
            });
            if (birthdateInput.classList.contains('is-invalid')) alt.classList.add('is-invalid');
            if (birthdateInput.getAttribute('aria-invalid')) alt.setAttribute('aria-invalid', 'true');
            if (birthdateInput.getAttribute('aria-describedby')) alt.setAttribute('aria-describedby', birthdateInput.getAttribute('aria-describedby'));
            alt.id = 'birthdate_display';
            document.querySelector('label[for="birthdate"]')?.setAttribute('for', 'birthdate_display');

            // Year dropdown instead of the small number box
            const monthBox = fp.calendarContainer.querySelector('.flatpickr-current-month');
            monthBox.querySelector('.numInputWrapper').style.display = 'none';
            // Custom year list (opens inside the calendar, never off-screen like the native list)
            const yearWrap = document.createElement('div');
            yearWrap.className = 'fp-year';
            const yearBtn = document.createElement('button');
            yearBtn.type = 'button';
            yearBtn.className = 'fp-year-btn';
            yearBtn.setAttribute('aria-label', 'Year');
            yearBtn.setAttribute('aria-haspopup', 'listbox');
            yearBtn.setAttribute('aria-expanded', 'false');
            yearBtn.appendChild(document.createTextNode(''));
            const yearList = document.createElement('ul');
            yearList.className = 'fp-year-list';
            yearList.setAttribute('role', 'listbox');
            yearList.hidden = true;
            for (let y = fp.config.maxDate.getFullYear(); y >= fp.config.minDate.getFullYear(); y--) {
                const li = document.createElement('li');
                li.textContent = String(y);
                li.dataset.year = String(y);
                li.setAttribute('role', 'option');
                yearList.appendChild(li);
            }
            const setYearList = open => {
                yearList.hidden = !open;
                yearBtn.setAttribute('aria-expanded', String(open));
                if (open) {
                    // scroll only the list itself (scrollIntoView could also scroll the page)
                    const on = yearList.querySelector('.is-active');
                    if (on) yearList.scrollTop = on.offsetTop - (yearList.clientHeight - on.offsetHeight) / 2;
                }
            };
            yearBtn.addEventListener('click', e => { e.stopPropagation(); setYearList(yearList.hidden); });
            yearList.addEventListener('click', e => {
                const li = e.target.closest('li');
                if (!li) return;
                fp.changeYear(parseInt(li.dataset.year, 10));
                setYearList(false);
            });
            fp.calendarContainer.addEventListener('click', e => { if (!yearWrap.contains(e.target)) setYearList(false); });
            yearWrap.append(yearBtn, yearList);
            monthBox.appendChild(yearWrap);
            fp._yearBtn = yearBtn;
            fp._yearList = yearList;
            fp._closeYearList = () => setYearList(false);
            syncYear(fp);

            // Footer: Clear
            const footer = document.createElement('div');
            footer.className = 'fp-footer';
            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.textContent = 'Clear';
            clearBtn.addEventListener('click', () => { fp.clear(); agePreview.value = ''; fp.close(); });
            footer.appendChild(clearBtn);
            fp.calendarContainer.appendChild(footer);
        },
        onOpen(selected, dateStr, fp) {
            syncYear(fp);
            fp._closeYearList?.();
        },
        onMonthChange(selected, dateStr, fp) { syncYear(fp); },
        onYearChange(selected, dateStr, fp) { syncYear(fp); },
        onChange(selected, dateStr, fp) {
            if (dateStr) calculateAge(dateStr); else agePreview.value = '';
            // typing/choosing clears the red state and server message, like other fields
            [birthdateInput, fp.altInput].forEach(el => { el.classList.remove('is-invalid'); el.removeAttribute('aria-invalid'); });
            document.getElementById('birthdate-error')?.remove();
            if (typeof refreshStepErrors === 'function') refreshStepErrors();
        },
    });
}

// ===== NUMBERS ONLY ON CONTACT =====
document.getElementById('contact_number').addEventListener('input', function () {
    this.value = this.value.replace(/[^0-9]/g, '');
});

// ===== PASSWORD TOGGLE =====
function setupToggle(buttonId, inputId) {
    const btn   = document.getElementById(buttonId);
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');

    btn.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        icon.className = show ? 'bx bxs-hide' : 'bx bxs-show';
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        btn.setAttribute('aria-pressed', String(show));
    });
}
setupToggle('togglePassword', 'password');
setupToggle('toggleConfirm',  'password_confirmation');

// ===== LIVE "PASSWORDS MATCH" HINT =====
const confirmInput = document.getElementById('password_confirmation');
const matchHint    = document.getElementById('matchHint');

function updateMatchHint() {
    const pw = document.getElementById('password').value;
    const cf = confirmInput.value;

    if (!cf) {
        matchHint.className = 'field-hint';
        matchHint.innerHTML = '';
        confirmInput.setCustomValidity('');
        return;
    }

    const ok = pw === cf;
    matchHint.className = 'field-hint ' + (ok ? 'is-ok' : 'is-bad');
    matchHint.innerHTML = ok
        ? "<i class='bx bx-check-circle'></i> Passwords match"
        : "<i class='bx bx-x-circle'></i> Passwords do not match";
    confirmInput.setCustomValidity(ok ? '' : 'Passwords do not match.');
}
document.getElementById('password').addEventListener('input', updateMatchHint);
confirmInput.addEventListener('input', updateMatchHint);

// ===== PASSWORD STRENGTH CHECKER =====
const passwordInput  = document.getElementById('password');
const strengthWidget = document.getElementById('passwordStrength');
const strengthLabel  = document.getElementById('strengthLabel');
const bars           = [
    document.getElementById('bar1'),
    document.getElementById('bar2'),
    document.getElementById('bar3'),
    document.getElementById('bar4'),
];

const rules = {
    'check-length':  { test: v => v.length >= 8 },
    'check-upper':   { test: v => /[A-Z]/.test(v) },
    'check-lower':   { test: v => /[a-z]/.test(v) },
    'check-number':  { test: v => /[0-9]/.test(v) },
    'check-special': { test: v => /[@$!%*#?&]/.test(v) },
};

const strengthMap = [
    { label: '',         color: '' },
    { label: 'Weak',     color: 'active-weak' },
    { label: 'Fair',     color: 'active-fair' },
    { label: 'Good',     color: 'active-good' },
    { label: 'Strong',   color: 'active-strong' },
];

passwordInput.addEventListener('input', function () {
    const val = this.value;

    if (!val) {
        strengthWidget.style.display = 'none';
        return;
    }

    strengthWidget.style.display = 'block';

    // Check each rule
    let passed = 0;
    for (const [id, rule] of Object.entries(rules)) {
        const el   = document.getElementById(id);
        const icon = el.querySelector('i');
        if (rule.test(val)) {
            el.classList.add('passed');
            icon.className = 'bx bx-check-circle';
            passed++;
        } else {
            el.classList.remove('passed');
            icon.className = 'bx bx-x-circle';
        }
    }

    // Update strength bar (max 4 bars, based on 5 rules)
    const level = passed === 0 ? 0 : passed <= 1 ? 1 : passed <= 2 ? 2 : passed <= 3 ? 3 : passed <= 4 ? 3 : 4;
    bars.forEach((bar, i) => {
        bar.className = i < level ? strengthMap[level].color : '';
    });

    strengthLabel.textContent = level > 0
        ? 'Strength: ' + strengthMap[level].label
        : 'Enter a password';
    strengthLabel.style.color = level === 1 ? '#ef4444'
                               : level === 2 ? '#f97316'
                               : level === 3 ? '#eab308'
                               : level === 4 ? '#15803d'
                               : 'var(--text-muted)';
});

// ===== MULTI-STEP WIZARD =====
const wizardSteps   = Array.from(registrationForm.querySelectorAll('.wizard-step'));
const wizardStepper = document.getElementById('wizardStepper');
const wizardItems   = Array.from(wizardStepper.querySelectorAll('li'));
const wizardButtons = wizardItems.map(li => li.querySelector('.wizard-step-btn'));
const wizardBack    = document.getElementById('wizardBack');
const wizardNext    = document.getElementById('wizardNext');
const wizardSubmit  = registrationForm.querySelector('[data-wizard-submit]');
const wizardLive    = document.getElementById('wizardLive');
const lastStep      = wizardSteps.length;
const ERROR_SELECTOR = '.is-invalid, .field-error[id$="-error"]';

let currentStep = 1;
let maxReached  = 1;

// We validate step by step ourselves, so the browser must not silently block
// the submit because of a field that lives on a hidden step.
registrationForm.noValidate = true;

function fieldsOf(stepNo) {
    return Array.from(wizardSteps[stepNo - 1].querySelectorAll('input, select, textarea'))
        .filter(el => !el.disabled);
}

// Returns true when every field of the step is valid.
// With report = true it also shows the browser's message on the first bad field.
function validateStep(stepNo, report) {
    for (const el of fieldsOf(stepNo)) {
        if (el.checkValidity()) continue;

        if (report) {
            if (el.type === 'file') {
                // the real file input is hidden, so show our own message instead
                el.closest('.file-label')?.classList.add('is-invalid');
                showIdError('Please upload your valid ID.');
            } else {
                el.reportValidity();
            }
        }
        return false;
    }
    return true;
}

function paintStepper() {
    wizardItems.forEach((li, i) => {
        const n    = i + 1;
        const done = n !== currentStep && n <= maxReached && !li.classList.contains('has-error');

        li.classList.toggle('is-current', n === currentStep);
        li.classList.toggle('is-done', done);
        li.querySelector('.wizard-dot').innerHTML = done ? "<i class='bx bx-check'></i>" : String(n);

        wizardButtons[i].disabled = n > maxReached;
        if (n === currentStep) {
            wizardButtons[i].setAttribute('aria-current', 'step');
        } else {
            wizardButtons[i].removeAttribute('aria-current');
        }
    });
}

function goToStep(stepNo, focus = true) {
    if (stepNo < 1 || stepNo > lastStep) return;   // ignore out-of-range requests

    currentStep = stepNo;
    maxReached  = Math.max(maxReached, stepNo);

    wizardSteps.forEach((el, i) => el.classList.toggle('is-active', i + 1 === stepNo));
    paintStepper();

    wizardBack.hidden = stepNo === 1;
    wizardNext.hidden = stepNo === lastStep;
    wizardSubmit.classList.toggle('is-visible', stepNo === lastStep);

    wizardLive.textContent = 'Step ' + stepNo + ' of ' + lastStep + ': ' + wizardSteps[stepNo - 1].dataset.title;

    if (focus) {
        wizardSteps[stepNo - 1].focus({ preventScroll: true });
        wizardStepper.scrollIntoView({ block: 'start', behavior: 'smooth' });
    }
}

// Mark the stepper circles of steps that still contain a server error.
function refreshStepErrors() {
    wizardItems.forEach((li, i) => {
        li.classList.toggle('has-error', !!wizardSteps[i].querySelector(ERROR_SELECTOR));
    });
    paintStepper();
}

// --- buttons ---
wizardNext.addEventListener('click', () => {
    if (validateStep(currentStep, true)) goToStep(currentStep + 1);
});
wizardBack.addEventListener('click', () => goToStep(currentStep - 1));
wizardButtons.forEach((btn, i) => btn.addEventListener('click', () => {
    if (i + 1 <= maxReached) goToStep(i + 1);
}));

// Enter inside a field = "Next" (not an accidental submit) until the last step.
registrationForm.addEventListener('keydown', e => {
    if (e.key !== 'Enter' || currentStep === lastStep) return;
    if (e.target.matches('input:not([type="checkbox"]):not([type="file"]), select')) {
        e.preventDefault();
        wizardNext.click();
    }
});

// Typing in a field clears its red state / server message.
registrationForm.addEventListener('input', e => {
    const el = e.target;
    if (!el.name) return;

    el.classList.remove('is-invalid');
    el.removeAttribute('aria-invalid');
    document.getElementById(el.name + '-error')?.remove();
    refreshStepErrors();
});

// Final safety net: check every step in order before sending.
registrationForm.addEventListener('submit', e => {
    for (let n = 1; n <= lastStep; n++) {
        if (!validateStep(n, false)) {
            e.preventDefault();
            goToStep(n, false);
            validateStep(n, true);
            return;
        }
    }
});

// --- start position ---
const stepsWithErrors = wizardSteps
    .map((el, i) => (el.querySelector(ERROR_SELECTOR) ? i + 1 : 0))
    .filter(Boolean);
const hasRegistrationError = !!document.querySelector('[data-registration-error]');

if (stepsWithErrors.length || hasRegistrationError) {
    // The server sent the form back: the visitor already went through every
    // step, so let them move freely and open the first step that has a problem.
    maxReached = lastStep;
    wizardItems.forEach((li, i) => li.classList.toggle('has-error', stepsWithErrors.includes(i + 1)));
    goToStep(stepsWithErrors[0] || lastStep, false);

    const firstInvalid = wizardSteps[currentStep - 1].querySelector('.is-invalid');
    if (firstInvalid) {
        firstInvalid.scrollIntoView({ block: 'center', behavior: 'smooth' });
        if (firstInvalid.matches('input, select, textarea')) {
            firstInvalid.focus({ preventScroll: true });
        }
    }
} else {
    goToStep(1, false);
}
</script>

</body>
</html>