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

            <ul class="left-steps">
                <li><i class='bx bx-check'></i> Fill in your personal details</li>
                <li><i class='bx bx-check'></i> Upload a valid ID for verification</li>
                <li><i class='bx bx-check'></i> Set your login credentials</li>
                <li><i class='bx bx-check'></i> Start booking appointments</li>
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

            <!-- ===== PROFILE PICTURE ===== -->
            <div class="section-header">
                <div class="section-header-icon"><i class='bx bxs-camera'></i></div>
                <span>Profile Picture</span>
            </div>

            <div class="input-group">
                <label class="file-label">
                    <i class='bx bx-image-add'></i>
                    <span id="avatarLabel">Click to upload profile photo (JPG, PNG)</span>
                    <input type="file" name="avatar" accept="image/jpg,image/jpeg,image/png"
                           onchange="document.getElementById('avatarLabel').textContent = this.files[0]?.name || 'Click to upload profile photo'">
                </label>
            </div>

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
                        placeholder="09XXXXXXXXX" maxlength="11" required>
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
                <label class="file-label">
                    <i class='bx bx-upload'></i>
                    <span id="idLabel">Click to upload your valid ID (JPG, PNG)</span>
                    <input type="file" name="valid_id" accept="image/jpg,image/jpeg,image/png" required
                           onchange="document.getElementById('idLabel').textContent = this.files[0]?.name || 'Click to upload your valid ID'">
                </label>
            </div>

            <!-- ===== REASON FOR REGISTRATION ===== -->
            <div class="section-header">
                <div class="section-header-icon"><i class='bx bxs-notepad'></i></div>
                <span>Reason for Registration</span>
            </div>

            <div class="input-group">
                <label class="input-label">Purpose *</label>
                <div class="input-wrap">
                    <i class='bx bxs-info-circle input-icon'></i>
                    <select name="reason" required>
                        <option value="">-- Select Reason --</option>
                        <option value="To Book Appointments Online" {{ old('reason')=='To Book Appointments Online' ? 'selected':'' }}>To Book Appointments Online</option>
                        <option value="To Access Clinic Services"   {{ old('reason')=='To Access Clinic Services'     ? 'selected':'' }}>To Access Clinic Services</option>
                        <option value="To Manage Personal Health Records"   {{ old('reason')=='To Manage Personal Health Records'   ? 'selected':'' }}>To Manage Personal Health Records</option>
                        <option value="For Easier Communication with the Clinic" {{ old('reason')=='For Easier Communication with the Clinic' ? 'selected':'' }}>For Easier Communication with the Clinic</option>
                        <option value="Others"                  {{ old('reason')=='Others'                  ? 'selected':'' }}>Others</option>
                    </select>
                </div>
            </div>

            <!-- ===== MEDICAL INFORMATION ===== -->
            <div class="section-header">
                <div class="section-header-icon"><i class='bx bxs-heart-circle'></i></div>
                <span>Medical Information</span>
            </div>

            <div class="input-group">
                <label class="input-label">Blood Type *</label>
                <div class="input-wrap">
                    <i class='bx bx-droplet input-icon'></i>
                    <select name="blood_type" required>
                        <option value="">-- Select Blood Type --</option>
                        <option value="Unknown" {{ old('blood_type')=='Unknown' ? 'selected':'' }}>Unknown / Not Sure</option>
                        <option value="A+"  {{ old('blood_type')=='A+'  ? 'selected':'' }}>A+</option>
                        <option value="A-"  {{ old('blood_type')=='A-'  ? 'selected':'' }}>A-</option>
                        <option value="B+"  {{ old('blood_type')=='B+'  ? 'selected':'' }}>B+</option>
                        <option value="B-"  {{ old('blood_type')=='B-'  ? 'selected':'' }}>B-</option>
                        <option value="AB+" {{ old('blood_type')=='AB+' ? 'selected':'' }}>AB+</option>
                        <option value="AB-" {{ old('blood_type')=='AB-' ? 'selected':'' }}>AB-</option>
                        <option value="O+"  {{ old('blood_type')=='O+'  ? 'selected':'' }}>O+</option>
                        <option value="O-"  {{ old('blood_type')=='O-'  ? 'selected':'' }}>O-</option>
                    </select>
                </div>
            </div>

            <div class="input-group">
                <label class="input-label">Known Allergies</label>
                <div class="input-wrap textarea-wrap">
                    <i class='bx bxs-virus input-icon'></i>
                    <textarea name="allergies" placeholder="List any existing allergies (e.g. Penicillin, Shellfish, Dust)">{{ old('allergies') }}</textarea>
                </div>
            </div>

            <!-- ===== EMERGENCY CONTACT ===== -->
            <div class="section-header">
                <div class="section-header-icon"><i class='bx bxs-phone-call'></i></div>
                <span>Emergency Contact Information</span>
            </div>

            <div class="form-row">
                <div class="input-group">
                    <label class="input-label">Full Name *</label>
                    <div class="input-wrap">
                        <i class='bx bxs-user input-icon'></i>
                        <input type="text" name="emergency_name" placeholder="Full name" value="{{ old('emergency_name') }}" required>
                    </div>
                </div>
                <div class="input-group">
                    <label class="input-label">Relationship *</label>
                    <div class="input-wrap">
                        <i class='bx bxs-group input-icon'></i>
                        <input type="text" name="relationship" placeholder="e.g. Parent, Spouse" value="{{ old('relationship') }}" required>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="input-group">
                    <label class="input-label">Contact Number *</label>
                    <div class="input-wrap">
                        <i class='bx bxs-phone input-icon'></i>
                        <input type="tel" name="emergency_contact_number"
                            placeholder="09XXXXXXXXX" maxlength="11"
                            value="{{ old('emergency_contact_number') }}" required>
                    </div>
                </div>
                <div class="input-group">
                    <label class="input-label">Address *</label>
                    <div class="input-wrap">
                        <i class='bx bxs-map input-icon'></i>
                        <input type="text" name="emergency_address" placeholder="Complete address" value="{{ old('emergency_address') }}" required>
                    </div>
                </div>
            </div>

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

            <div class="form-row">
                <div class="input-group">
                    <label class="input-label">Password *</label>
                    <div class="input-wrap">
                        <i class='bx bxs-lock-alt input-icon'></i>
                        <input type="password" id="password" name="password" placeholder="Min. 6 characters" required>
                        <i class='bx bxs-show input-toggle' id="togglePassword"></i>
                    </div>
                </div>
                <div class="input-group">
                    <label class="input-label">Confirm Password *</label>
                    <div class="input-wrap">
                        <i class='bx bxs-lock input-icon'></i>
                        <input type="password" name="password_confirmation" placeholder="Re-enter password" required>
                    </div>
                </div>
            </div>

            <button type="submit" class="register-btn">
                <i class='bx bx-user-plus'></i> Create Account
            </button>
        </form>

    </div>
</div>

<script>
// Age auto-fill
const birthdateInput = document.getElementById('birthdate');
const agePreview     = document.getElementById('agePreview');

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

// Numbers only on contact
document.getElementById('contact_number').addEventListener('input', function () {
    this.value = this.value.replace(/[^0-9]/g, '');
});

// Password toggle
const toggle   = document.getElementById('togglePassword');
const password = document.getElementById('password');

toggle.addEventListener('click', () => {
    const isHidden = password.type === 'password';
    password.type  = isHidden ? 'text' : 'password';
    toggle.classList.toggle('bxs-show', !isHidden);
    toggle.classList.toggle('bxs-hide',  isHidden);
});
</script>

</body>
</html>