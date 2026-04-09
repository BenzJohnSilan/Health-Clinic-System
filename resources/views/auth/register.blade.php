    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <title>Register</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: "Segoe UI", sans-serif;
    }

    body {
        background: linear-gradient(to right, #e6ebf5, #f2f4f8);
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }

    /* MAIN WRAPPER */
    .wrapper {
        display: flex;
        width: 950px;
        height: 520px;
        background: #f5f7fb;
        border-radius: 25px;
        overflow: hidden;
        box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    }

    /* LEFT SIDE */
    .left {
        width: 50%;
        background: linear-gradient(135deg, #6a11cb, #a044ff);
        color: white;

        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        padding: 60px 40px;

        border-top-right-radius: 0;
        border-bottom-right-radius: 140px;
        position: relative;
    }

    .left::after {
        content: "";
        position: absolute;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
        top: -60px;
        right: -60px;
    }

    .left h1 {
        font-size: 28px;
        line-height: 1.4;
        margin-bottom: 25px;
    }

    .left h2 {
        font-size: 25px;
        margin-bottom: 10px;
    }

    .left p {
        font-size: 14px;
        opacity: 0.9;
        margin-bottom: 25px;
    }

    .left button {
        padding: 12px 35px;
        border-radius: 30px;
        border: 2px solid #fff;
        background: transparent;
        color: white;
        font-weight: 500;
        cursor: pointer;
        transition: 0.3s;
    }

    .left button:hover {
        background: white;
        color: #6a11cb;
    }

    /* RIGHT SIDE */
    .right {
        width: 50%;
        padding: 50px 40px;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        overflow-y: auto;
    }

    /* TITLE */
    .right h2 {
        text-align: center;
        margin-bottom: 20px;
        font-size: 26px;
        color: #333;
    }

    /* ERROR / SUCCESS MESSAGES */
    .messages {
        text-align: center;
        margin-bottom: 15px;
    }

    .messages .error {
        color: red;
        font-size: 13px;
        margin-bottom: 5px;
    }

    .messages .success {
        color: green;
        font-size: 13px;
        margin-bottom: 5px;
    }

    /* INPUT */
    .input-group {
        position: relative;
        margin-bottom: 18px;
    }

    .input-group input, 
    .input-group select {
        width: 100%;
        padding: 12px 45px 12px 15px;
        border-radius: 30px;
        border: none;
        background: #eef1f6;
        outline: none;
        font-size: 14px;
    }

    .input-group i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #777;
    }

    .input-group .toggle {
        left: auto;
        right: 15px;
        cursor: pointer;
    }

    /* SECTION HEADER */
    .section-header {
        font-weight: 600;
        margin: 20px 0 10px 0;
        color: #6a11cb;
        font-size: 14px;
    }

    /* BUTTON */
    .register-btn {
        width: 100%;
        padding: 13px;
        border-radius: 30px;
        border: none;
        background: linear-gradient(135deg, #6a11cb, #a044ff);
        color: white;
        font-size: 15px;
        font-weight: 500;
        cursor: pointer;
        margin-top: 15px;
    }

    /* LINK */
    .login-link {
        text-align: center;
        margin-top: 15px;
        font-size: 14px;
        color: #555;
    }

    /* SCROLLBAR */
    .right::-webkit-scrollbar {
        width: 6px;
    }

    .right::-webkit-scrollbar-thumb {
        background: #6a11cb;;
        border-radius: 10px;
    }

    /* RESPONSIVE */
    @media (max-width: 768px) {
        .wrapper {
            flex-direction: column;
            height: auto;
        }

        .left {
            border-radius: 0;
        }

        .right {
            width: 100%;
            padding: 30px 20px;
        }
    }
    </style>
    </head>

    <body>

    <div class="wrapper">

        <!-- LEFT -->
        <div class="left">
            <h1>Health Clinic Record<br>Management System</h1>
            <h2>Welcome!</h2>
            <p>Already have an account?</p>
            <button onclick="window.location.href='{{ route('login') }}'">Login</button>
        </div>

        <!-- RIGHT -->
        <div class="right">
            <h2>Create Account</h2>

            <!-- ERROR / SUCCESS MESSAGES -->
            <div class="messages">
                @if ($errors->any())
                    @foreach ($errors->all() as $error)
                        <div class="error">{{ $error }}</div>
                    @endforeach
                @endif

                @if(session('success'))
                    <div class="success">{{ session('success') }}</div>
                @endif
            </div>

            <form method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data">
                @csrf

                <!-- PROFILE -->
                <div class="input-group section-header">
                    Profile Picture
                </div>
                <div class="input-group">
                    <input type="file" name="avatar">
                </div>

                <!-- PERSONAL INFO -->
                <div class="input-group section-header">Personal Info</div>
                <div class="input-group"><input name="first_name" placeholder="First Name" required></div>
                <div class="input-group"><input name="middle_name" placeholder="Middle Name"></div>
                <div class="input-group"><input name="last_name" placeholder="Last Name" required></div>
                <div class="input-group"><input name="suffix" placeholder="Suffix"></div>
                <div class="input-group"><input type="date" name="birthdate" id="birthdate" required></div>
                <div class="input-group"><input type="text" id="agePreview" placeholder="Age" readonly></div>
                <div class="input-group">
                    <select name="gender" required>
                        <option value="">Gender</option>
                        <option>Male</option>
                        <option>Female</option>
                    </select>
                </div>
                <div class="input-group">
                    <select name="civil_status" required>
                        <option value="">Civil Status</option>
                        <option>Single</option>
                        <option>Married</option>
                    </select>
                </div>
                <div class="input-group"><input name="address" placeholder="Address" required></div>
                <div class="input-group"><input name="contact_number" placeholder="Contact Number" required></div>

                <!-- LOGIN INFO -->
                <div class="input-group section-header">Login Info</div>
                <div class="input-group"><input name="username" placeholder="Username" required></div>
                <div class="input-group"><input type="email" name="email" placeholder="Email" required></div>
                <div class="input-group">
                    <input type="password" id="password" name="password" placeholder="Password" required>
                    <i class='bx bxs-show toggle' id="togglePassword"></i>
                </div>
                <div class="input-group"><input type="password" name="password_confirmation" placeholder="Confirm Password" required></div>

                <button type="submit" class="register-btn">Register</button>
            </form>
        </div>

    </div>

    <script>
    // Calculate age
    const birthdateInput = document.getElementById('birthdate');
    const agePreview = document.getElementById('agePreview');
    birthdateInput.addEventListener('change', function () {
        const birthdate = new Date(this.value);
        const today = new Date();
        let age = today.getFullYear() - birthdate.getFullYear();
        const m = today.getMonth() - birthdate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthdate.getDate())) {
            age--;
        }
        agePreview.value = age;
    });

    // Toggle password show/hide
    const toggle = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    toggle.addEventListener('click', () => {
        const type = password.type === 'password' ? 'text' : 'password';
        password.type = type;
        toggle.classList.toggle('bxs-show');
        toggle.classList.toggle('bxs-hide');
    });
    </script>

    </body>
    </html>