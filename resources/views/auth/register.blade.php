<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — ClinicRMS</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/register.css') }}">

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

            <ul class="left-steps" aria-label="Registration progress">
                <li data-progress="personal"><i class='bx bx-check'></i> Fill in your personal details</li>
                <li data-progress="verification"><i class='bx bx-check'></i> Upload a valid ID for verification</li>
                <li data-progress="credentials"><i class='bx bx-check'></i> Set your login credentials</li>
                <li data-progress="ready"><i class='bx bx-check'></i> Start booking appointments</li>
            </ul>

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
        <div class="messages">
            @if($errors->any())
                @foreach($errors->all() as $error)
                    <div class="alert-error"><i class='bx bx-error-circle'></i> {{ $error }}</div>
                @endforeach
            @endif
            @if(session('success'))
                <div class="alert-success"><i class='bx bx-check-circle'></i> {{ session('success') }}</div>
            @endif
        </div>

        <form method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data">
            @csrf

            <!-- ===== PERSONAL INFORMATION ===== -->
            <div class="section-header">
                <div class="section-header-icon"><i class='bx bxs-user-detail'></i></div>
                <span>Personal Information</span>
            </div>

            <div class="form-row">
                <div class="input-group">
                    <label class="input-label">First Name *</label>
                    <div class="input-wrap">
                        <i class='bx bxs-user input-icon'></i>
                        <input type="text" name="first_name" placeholder="Juan" value="{{ old('first_name') }}" required>
                    </div>
                </div>
                <div class="input-group">
                    <label class="input-label">Middle Name</label>
                    <div class="input-wrap">
                        <i class='bx bxs-user input-icon'></i>
                        <input type="text" name="middle_name" placeholder="Santos" value="{{ old('middle_name') }}">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="input-group">
                    <label class="input-label">Last Name *</label>
                    <div class="input-wrap">
                        <i class='bx bxs-user input-icon'></i>
                        <input type="text" name="last_name" placeholder="dela Cruz" value="{{ old('last_name') }}" required>
                    </div>
                </div>
                <div class="input-group">
                    <label class="input-label">Suffix</label>
                    <div class="input-wrap">
                        <i class='bx bxs-tag input-icon'></i>
                        <input type="text" name="suffix" placeholder="Jr., Sr., III" value="{{ old('suffix') }}">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="input-group">
                    <label class="input-label">Birthdate *</label>
                    <div class="input-wrap">
                        <i class='bx bxs-calendar input-icon'></i>
                        <input type="date" name="birthdate" id="birthdate" value="{{ old('birthdate') }}" required>
                    </div>
                </div>
                <div class="input-group">
                    <label class="input-label">Age</label>
                    <div class="input-wrap">
                        <i class='bx bxs-hourglass input-icon'></i>
                        <input type="text" id="agePreview" placeholder="Auto-filled" readonly
                               style="background: var(--card-bg); cursor: not-allowed;">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="input-group">
                    <label class="input-label">Gender *</label>
                    <div class="input-wrap">
                        <i class='bx bx-male-female input-icon'></i>
                        <select name="gender" required>
                            <option value="">-- Select Gender --</option>
                            <option value="Male"   {{ old('gender')=='Male'   ? 'selected':'' }}>Male</option>
                            <option value="Female" {{ old('gender')=='Female' ? 'selected':'' }}>Female</option>
                            <option value="Other"  {{ old('gender')=='Other'  ? 'selected':'' }}>Other</option>
                        </select>
                    </div>
                </div>
                <div class="input-group">
                    <label class="input-label">Civil Status *</label>
                    <div class="input-wrap">
                        <i class='bx bxs-heart input-icon'></i>
                        <select name="civil_status" required>
                            <option value="">-- Select Status --</option>
                            <option value="Single"    {{ old('civil_status')=='Single'    ? 'selected':'' }}>Single</option>
                            <option value="Married"   {{ old('civil_status')=='Married'   ? 'selected':'' }}>Married</option>
                            <option value="Widowed"   {{ old('civil_status')=='Widowed'   ? 'selected':'' }}>Widowed</option>
                            <option value="Separated" {{ old('civil_status')=='Separated' ? 'selected':'' }}>Separated</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="input-group">
                <label class="input-label">Complete Address *</label>
                <div class="input-wrap">
                    <i class='bx bxs-map input-icon'></i>
                    <input type="text" name="address" placeholder="House No., Street, Barangay, City, Province" value="{{ old('address') }}" required>
                </div>
            </div>

            <div class="input-group">
                <label class="input-label">Contact Number *</label>
                <div class="input-wrap">
                    <i class='bx bxs-phone input-icon'></i>
                    <input type="tel" name="contact_number" id="contact_number"
                        placeholder="09XXXXXXXXX" maxlength="11"
                        value="{{ old('contact_number') }}" required>
                </div>
            </div>

            <!-- ===== VERIFICATION INFORMATION ===== -->
            <div class="section-header">
                <div class="section-header-icon"><i class='bx bxs-id-card'></i></div>
                <span>Verification Information</span>
            </div>

            <div class="input-group">
                <label class="input-label">ID Type *</label>
                <div class="input-wrap">
                    <i class='bx bxs-credit-card input-icon'></i>
                    <select name="id_type" required>
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
            </div>

            <div class="input-group">
                <label class="input-label">Upload Valid ID *</label>
                @if(session('temp_valid_id'))
                    {{-- Show preview of previously uploaded valid ID --}}
                    <div class="file-preview">
                        <img src="{{ asset('storage/' . session('temp_valid_id')) }}" alt="Valid ID Preview" class="file-preview-img">
                        <div class="file-preview-info">
                            <i class='bx bx-check-circle' style="color:#22c55e; font-size:16px;"></i>
                            <span>Valid ID uploaded</span>
                        </div>
                        <label class="file-reupload-btn">
                            <i class='bx bx-refresh'></i> Change ID
                            <input type="file" name="valid_id" accept="image/jpg,image/jpeg,image/png" style="display:none;"
                                   onchange="previewFile(this, null, 'idLabel')">
                        </label>
                    </div>
                @else
                    <label class="file-label">
                        <i class='bx bx-upload'></i>
                        <span id="idLabel">Click to upload your valid ID (JPG, PNG)</span>
                        <input type="file" name="valid_id" accept="image/jpg,image/jpeg,image/png" required
                               onchange="previewFile(this, null, 'idLabel')">
                    </label>
                @endif
            </div>

            {{-- Profile Picture and Reason for Registration are no longer
                 collected during registration. Profile Picture can be added
                 later by the patient via the Patient Profile page (Account
                 Settings). Medical Information and Emergency Contact are
                 also completed there before booking their first appointment. --}}

            <!-- ===== LOGIN INFORMATION ===== -->
            <div class="section-header">
                <div class="section-header-icon"><i class='bx bxs-lock-alt'></i></div>
                <span>Login Information</span>
            </div>

            <div class="form-row">
                <div class="input-group">
                    <label class="input-label">Username *</label>
                    <div class="input-wrap">
                        <i class='bx bxs-user-circle input-icon'></i>
                        <input type="text" name="username" placeholder="Choose a username" value="{{ old('username') }}" required>
                    </div>
                </div>
                <div class="input-group">
                    <label class="input-label">Email Address *</label>
                    <div class="input-wrap">
                        <i class='bx bxs-envelope input-icon'></i>
                        <input type="email" name="email" placeholder="your@email.com" value="{{ old('email') }}" required>
                    </div>
                </div>
            </div>

            <!-- PASSWORD WITH STRENGTH CHECKER -->
            <div class="input-group">
                <label class="input-label">Password *</label>
                <div class="input-wrap">
                    <i class='bx bxs-lock-alt input-icon'></i>
                    <input type="password" id="password" name="password" placeholder="Min. 8 characters" required>
                    <i class='bx bxs-show input-toggle' id="togglePassword"></i>
                </div>

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
                <label class="input-label">Confirm Password *</label>
                <div class="input-wrap">
                    <i class='bx bxs-lock input-icon'></i>
                    <input type="password" name="password_confirmation" id="password_confirmation" placeholder="Re-enter password" required>
                    <i class='bx bxs-show input-toggle' id="toggleConfirm"></i>
                </div>
            </div>

            <button type="submit" class="register-btn">
                <i class='bx bx-user-plus'></i> Create Account
            </button>
        </form>

    </div>
</div>

<script>
// ===== FILE PREVIEW HELPER =====
function previewFile(input, imgId, labelId) {
    const file = input.files[0];
    if (!file) return;

    // Update label text
    if (labelId && document.getElementById(labelId)) {
        document.getElementById(labelId).textContent = file.name;
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

// ===== REGISTRATION PROGRESS =====
const registrationForm = document.querySelector('form');
const progressSteps = {
    personal: document.querySelector('[data-progress="personal"]'),
    verification: document.querySelector('[data-progress="verification"]'),
    credentials: document.querySelector('[data-progress="credentials"]'),
    ready: document.querySelector('[data-progress="ready"]'),
};

function hasValue(name) {
    return registrationForm.elements[name]?.value.trim().length > 0;
}

function setProgressStep(step, complete) {
    const item = progressSteps[step];
    item.classList.toggle('is-complete', complete);
    item.querySelector('i').className = complete ? 'bx bxs-check-circle' : 'bx bx-check';
}

function updateRegistrationProgress() {
    const personalComplete = ['first_name', 'last_name', 'birthdate', 'gender', 'civil_status', 'address', 'contact_number']
        .every(hasValue);
    const validIdInput = registrationForm.elements.valid_id;
    const hasUploadedId = document.querySelector('.file-preview') || validIdInput?.files.length > 0;
    const verificationComplete = hasValue('id_type') && hasUploadedId;
    const credentialsComplete = hasValue('username') && hasValue('email') &&
        registrationForm.elements.password.value.length >= 8 &&
        registrationForm.elements.password.value === registrationForm.elements.password_confirmation.value;

    setProgressStep('personal', personalComplete);
    setProgressStep('verification', verificationComplete);
    setProgressStep('credentials', credentialsComplete);
    setProgressStep('ready', personalComplete && verificationComplete && credentialsComplete);
}

registrationForm.addEventListener('input', updateRegistrationProgress);
registrationForm.addEventListener('change', updateRegistrationProgress);
updateRegistrationProgress();

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

// ===== NUMBERS ONLY ON CONTACT =====
document.getElementById('contact_number').addEventListener('input', function () {
    this.value = this.value.replace(/[^0-9]/g, '');
});

// ===== PASSWORD TOGGLE =====
function setupToggle(toggleId, inputId) {
    const toggle = document.getElementById(toggleId);
    const input  = document.getElementById(inputId);
    toggle.addEventListener('click', () => {
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        toggle.classList.toggle('bxs-show', !isHidden);
        toggle.classList.toggle('bxs-hide',  isHidden);
    });
}
setupToggle('togglePassword', 'password');
setupToggle('toggleConfirm',  'password_confirmation');

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
</script>

</body>
</html>
