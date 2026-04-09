<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login</title>
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

/* MAIN */
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

/* circle */
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

/* TEXT */
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

/* BUTTON CENTERED */
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
    padding: 60px 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
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

.input-group input {
    width: 100%;
    padding: 12px 45px;
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

/* OPTIONS */
.options {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    margin-bottom: 20px;
    color: #666;
}

/* BUTTON */
.login-btn {
    width: 100%;
    padding: 13px;
    border-radius: 30px;
    border: none;
    background: linear-gradient(135deg, #6a11cb, #a044ff);
    color: white;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
}

/* SOCIAL */
.social {
    text-align: center;
    margin-top: 20px;
}

.social p {
    font-size: 13px;
    color: #777;
    margin-bottom: 10px;
}

.social i {
    margin: 5px;
    padding: 10px;
    border-radius: 50%;
    border: 1px solid #ccc;
    cursor: pointer;
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
    }
}
</style>
</head>

<body>

<div class="wrapper">

    <!-- LEFT -->
    <div class="left">
        <h1>Health Clinic Record<br>Management System</h1>
        <h2>Hello, Welcome!</h2>
        <p>Don't have an account?</p>
        <button onclick="window.location.href='{{ route('register') }}'">Register</button>
    </div>

    <!-- RIGHT -->
    <div class="right">
        <h2>Login</h2>

        <!-- ERROR / SUCCESS MESSAGES -->
        <div class="messages">
            @error('login') <div class="error">{{ $message }}</div> @enderror
            @error('password') <div class="error">{{ $message }}</div> @enderror
            @error('role') <div class="error">{{ $message }}</div> @enderror

            @if(session('error'))
                <div class="error">{{ session('error') }}</div>
            @endif
            @if(session('success'))
                <div class="success">{{ session('success') }}</div>
            @endif
        </div>

        <form method="POST" action="{{ route('login.authenticate') }}">
            @csrf

            <div class="input-group">
                <i class='bx bxs-user'></i>
                <input type="text" name="login" placeholder="Username or Email" required>
            </div>

            <div class="input-group">
                <i class='bx bxs-lock-alt'></i>
                <input type="password" id="password" name="password" placeholder="Password" required>
                <i class='bx bxs-show toggle' id="togglePassword"></i>
            </div>

            <div class="options">
                <label><input type="checkbox"> Remember Me</label>
                <span>Forgot Password?</span>
            </div>

            <button type="submit" class="login-btn">Login</button>
        </form>

        <div class="social">
            <p>or login with social platforms</p>
            <i class='bx bxl-google'></i>
            <i class='bx bxl-facebook'></i>
            <i class='bx bxl-github'></i>
            <i class='bx bxl-linkedin'></i>
        </div>
    </div>

</div>

<script>
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