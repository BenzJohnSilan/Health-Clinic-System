<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Health Clinic Record Management System</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/landing-page.css') }}">
</head>

<body>

<!-- ===== NAVBAR ===== -->
<nav>
    <a class="nav-brand" href="#home">
        <i class='bx bx-plus-medical' style="font-size:18px; vertical-align:middle;"></i>
        ClinicRMS
    </a>

    <ul class="nav-center">
        <li><a href="#home">Home</a></li>
        <li><a href="#services">Services</a></li>
        <li><a href="#about">About</a></li>
        <li><a href="#faqs">FAQs</a></li>
        <li><a href="#contact">Contact</a></li>
        <li><a href="#location">Location</a></li>
    </ul>

    <div class="nav-links">
        <a href="{{ route('login') }}" class="btn-nav-outline">Login</a>
        <a href="{{ route('register') }}" class="btn-nav-fill">Register</a>
    </div>
</nav>

<!-- ===== HERO ===== -->
<div id="home" class="hero">
    <div class="hero-left">
        <div class="hero-badge">
            <i class='bx bxs-shield-plus'></i>
            Health Clinic Record Management
        </div>

        <h1>
            Smarter Clinic<br>
            Management <span>Starts Here</span>
        </h1>

        <p>
            Manage patient records, appointments, and clinic operations
            effortlessly — all in one secure and easy-to-use platform.
        </p>

        <div class="hero-buttons">
            <a href="{{ route('login') }}" class="btn-primary-hero">
                Get Started <i class='bx bx-right-arrow-alt'></i>
            </a>
            <a href="{{ route('register') }}" class="btn-secondary-hero">
                <i class='bx bx-user-plus'></i> Create Account
            </a>
        </div>
    </div>

    <div class="hero-right">
        <div class="hero-card">
            <div class="hero-card-header">
                <div class="hero-card-avatar"><i class='bx bxs-clinic'></i></div>
                <div>
                    <h4>Clinic Dashboard</h4>
                    <p>Live appointment overview</p>
                </div>
            </div>

            <div class="stat-row">
                <div class="stat-box">
                    <div class="stat-num">128</div>
                    <div class="stat-label">Total Patients</div>
                </div>
                <div class="stat-box">
                    <div class="stat-num">24</div>
                    <div class="stat-label">Today's Appts</div>
                </div>
            </div>

            <div class="appt-item">
                <div class="appt-dot" style="background:#6a11cb;"></div>
                <div class="appt-info">
                    <p>Juan dela Cruz</p>
                    <span>9:00 AM · Dr. Santos</span>
                </div>
                <span class="appt-badge badge-approved">Approved</span>
            </div>
            <div class="appt-item">
                <div class="appt-dot" style="background:#a044ff;"></div>
                <div class="appt-info">
                    <p>Maria Reyes</p>
                    <span>10:30 AM · Dr. Santos</span>
                </div>
                <span class="appt-badge badge-pending">Pending</span>
            </div>
            <div class="appt-item">
                <div class="appt-dot" style="background:#6a11cb;"></div>
                <div class="appt-info">
                    <p>Pedro Bautista</p>
                    <span>1:00 PM · Dr. Santos</span>
                </div>
                <span class="appt-badge badge-approved">Approved</span>
            </div>
        </div>
    </div>
</div>

<!-- ===== SERVICES ===== -->
<section id="services">
    <p class="section-label">What We Offer</p>
    <h2 class="section-title">Our Services</h2>
    <p class="section-sub">Comprehensive tools designed to keep your clinic running smoothly and your patients well-cared for.</p>

    <div class="services-grid">
        <div class="service-card">
            <div class="service-icon"><i class='bx bxs-folder-open'></i></div>
            <h4>Patient Records</h4>
            <p>Store and manage complete patient information securely in one centralized and easily accessible system.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class='bx bxs-calendar-check'></i></div>
            <h4>Appointment Scheduling</h4>
            <p>Book, track, and manage clinic appointments with real-time slot availability and conflict prevention.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class='bx bxs-lock-alt'></i></div>
            <h4>Secure Role Access</h4>
            <p>Role-based login for admins, doctors, and patients — each with controlled access to relevant data.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class='bx bxs-report'></i></div>
            <h4>Medical Reports</h4>
            <p>Generate detailed patient visit reports and medical histories for informed clinical decision-making.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class='bx bxs-bell-ring'></i></div>
            <h4>Appointment Reminders</h4>
            <p>Automated status updates keep patients informed about their upcoming or approved appointments.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class='bx bxs-user-detail'></i></div>
            <h4>Doctor Management</h4>
            <p>Manage doctor profiles, schedules, and patient assignments all from a single admin dashboard.</p>
        </div>
    </div>
</section>

<!-- ===== ABOUT ===== -->
<section id="about">
    <div class="about-inner">
        <div class="about-left">
            <p class="section-label" style="text-align:left;">Who We Are</p>
            <h2>About Our <span>Clinic System</span></h2>
            <p>
                We are a dedicated team committed to modernizing healthcare administration.
                Our Clinic Record Management System was built to replace outdated paper-based
                processes with a fast, reliable, and secure digital platform.
            </p>
            <p>
                Whether you're a patient booking your first appointment or an admin overseeing
                daily operations, our system provides a smooth and intuitive experience tailored
                to every role in the clinic.
            </p>

            <div class="about-stats">
                <div class="about-stat-box">
                    <div class="num">500+</div>
                    <div class="lbl">Patients Served</div>
                </div>
                <div class="about-stat-box">
                    <div class="num">10+</div>
                    <div class="lbl">Doctors</div>
                </div>
                <div class="about-stat-box">
                    <div class="num">99%</div>
                    <div class="lbl">Uptime</div>
                </div>
            </div>
        </div>

        <div class="about-right">
            <div class="about-img-wrap">
                <i class='bx bxs-clinic'></i>
            </div>
        </div>
    </div>
</section>

<!-- ===== FAQs SECTION ===== -->
<section id="faqs">
    <p class="section-label">Got Questions?</p>
    <h2 class="section-title">Frequently Asked Questions</h2>
    <p class="section-sub">Find answers to the most common questions about our clinic system. Can't find what you're looking for? Chat with Viora!</p>

    <div class="faqs-inner">
        <!-- Category Tabs -->
        <div class="faq-categories">
            <button class="faq-cat-btn active" data-cat="about">About the System</button>
            <button class="faq-cat-btn" data-cat="appointment">Appointment Booking</button>
            <button class="faq-cat-btn" data-cat="account">Login / Account</button>
            <button class="faq-cat-btn" data-cat="doctors">Doctors & Services</button>
            <button class="faq-cat-btn" data-cat="privacy">Privacy & Security</button>
        </div>

        <!-- About the System -->
        <div class="faq-group active" id="faq-about">
            <div class="faq-item">
                <button class="faq-question">
                    What is ClinicRMS and what is it for?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>ClinicRMS is a digital system for managing patient records, appointment scheduling, and general clinic operations. It is designed to replace outdated paper-based records with a faster, more secure, and easier-to-use digital solution.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    Who can use ClinicRMS?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>The system supports three types of users: <strong>Admins</strong> who manage the entire clinic, <strong>Doctors</strong> who have access to their patients and schedules, and <strong>Patients</strong> who can book appointments and view their own records.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    Is using the system free?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Registering and using ClinicRMS as a patient is completely free. Clinics and healthcare providers may contact us for subscription plans suited to the size of their operations.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    Is the system available on mobile?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Yes! ClinicRMS is fully responsive, meaning it works beautifully on all devices — whether desktop, tablet, or smartphone. No app installation is required.</p></div>
            </div>
        </div>

        <!-- Appointment Booking -->
        <div class="faq-group" id="faq-appointment">
            <div class="faq-item">
                <button class="faq-question">
                    How do I book an appointment?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Log in to your account, go to the <strong>Appointments</strong> section, select your preferred doctor, date, and time, then submit your booking request. You will receive a notification once your appointment has been approved.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    How long does it take for an appointment to be approved?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Appointments are typically reviewed and approved within <strong>24 to 48 hours</strong>. You will receive a system notification whenever there is a change in the status of your booking.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    Can I cancel or reschedule an appointment?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Yes, you can cancel or request to reschedule your appointment through your account dashboard. We recommend doing so at least <strong>24 hours before</strong> your scheduled appointment to allow other patients the opportunity to book that slot.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    Can I book an appointment for someone else (e.g., my child or parent)?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Yes. From your account, you can book an appointment on behalf of your dependents. Please make sure to provide the correct name and information of the actual patient during the booking process.</p></div>
            </div>
        </div>

        <!-- Account Issues -->
        <div class="faq-group" id="faq-account">
            <div class="faq-item">
                <button class="faq-question">
                    I forgot my password. What should I do?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>On the login page, click <strong>"Forgot Password"</strong> and enter your registered email address. We will send you a link to reset your password. Also check your spam or junk folder if you don't see the email in your inbox.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    I can't log in to my account. What might be the issue?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>You may have entered an incorrect email or password. Make sure your email address has been verified. If you recently registered, check your inbox for a verification email. If the problem persists, contact us at <strong>support@clinicrms.com</strong>.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    How do I update my personal information in my account?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Go to <strong>Profile Settings</strong> after logging in. From there, you can edit your name, contact number, and other details. Make sure to save your changes when done.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    How do I register for ClinicRMS?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Click the <strong>"Register"</strong> button in the navigation bar or the hero section. Fill in your name, email address, and password. Verify your email through the link we send you, and you're ready to use the system.</p></div>
            </div>
        </div>

        <!-- Doctors & Services -->
        <div class="faq-group" id="faq-doctors">
            <div class="faq-item">
                <button class="faq-question">
                    What specialists are available at the clinic?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>We have general practitioners and various specialists on staff. For the most up-to-date list of available doctors, you can log in to your account and visit the <strong>Doctors</strong> section, or contact the clinic directly.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    What are the clinic's operating hours?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>The clinic is open <strong>Monday through Friday, 9:00 AM – 5:00 PM</strong> and <strong>Saturday, 9:00 AM – 12:00 PM</strong>. We are closed on Sundays and public holidays.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    Can I view all of my medical records in the system?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Yes. In your patient dashboard, you can view your appointment history, medical reports issued by your doctor, and your personal information. Access to other patients' records is strictly restricted.</p></div>
            </div>
        </div>

        <!-- Privacy & Security -->
        <div class="faq-group" id="faq-privacy">
            <div class="faq-item">
                <button class="faq-question">
                    Is my personal and medical information safe?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Yes. ClinicRMS uses <strong>encrypted connections (HTTPS)</strong> and role-based access control to ensure that your information is only accessible to authorized individuals. Your data is never shared with third parties without your consent.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    Who has access to my medical records?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Your medical records can only be accessed by your <strong>attending physician</strong> and authorized clinic staff. As a patient, you also have access to your own records. No other person can view your information without the proper authorization.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    Can I request to have my data deleted?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Yes. You may request the deletion of your account and personal data by contacting us at <strong>support@clinicrms.com</strong>. Please note that certain medical records may be required to be retained for a period of time in accordance with applicable legal and regulatory requirements.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    What should I do if I notice suspicious activity on my account?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Change your password immediately and contact us at <strong>support@clinicrms.com</strong> or call our hotline. Never share your login credentials with anyone to prevent unauthorized access to your account.</p></div>
            </div>
        </div>
    </div>
</section>

<!-- ===== CONTACT ===== -->
<section id="contact">
    <p class="section-label">Get In Touch</p>
    <h2 class="section-title">Contact Us</h2>
    <p class="section-sub">Have questions or need support? Reach out to us and we'll get back to you as soon as possible.</p>

    <div class="contact-inner">
        <div class="contact-info">
            <div class="contact-item">
                <div class="contact-icon"><i class='bx bxs-map'></i></div>
                <div>
                    <h5>Our Address</h5>
                    <p>Barangay Dayap, Calauan,<br>Laguna, Philippines</p>
                </div>
            </div>
            <div class="contact-item">
                <div class="contact-icon"><i class='bx bxs-phone-call'></i></div>
                <div>
                    <h5>Phone Number</h5>
                    <p>+63 917 123 4567<br>+63 2 8123 4567</p>
                </div>
            </div>
            <div class="contact-item">
                <div class="contact-icon"><i class='bx bxs-envelope'></i></div>
                <div>
                    <h5>Email Address</h5>
                    <p>support@clinicrms.com<br>admin@clinicrms.com</p>
                </div>
            </div>
            <div class="contact-item">
                <div class="contact-icon"><i class='bx bxs-time-five'></i></div>
                <div>
                    <h5>Clinic Hours</h5>
                    <p>Monday – Friday: 9:00 AM – 5:00 PM<br>Saturday: 9:00 AM – 12:00 PM</p>
                </div>
            </div>
        </div>

        <div class="contact-form">
            <div class="form-row">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" placeholder="Juan">
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" placeholder="dela Cruz">
                </div>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" placeholder="juan@email.com">
            </div>
            <div class="form-group">
                <label>Subject</label>
                <input type="text" placeholder="How can we help you?">
            </div>
            <div class="form-group">
                <label>Message</label>
                <textarea placeholder="Write your message here..."></textarea>
            </div>
            <button class="btn-send">
                Send Message <i class='bx bx-send'></i>
            </button>
        </div>
    </div>
</section>

<!-- ===== LOCATION ===== -->
<section id="location">
    <p class="section-label">Find Us</p>
    <h2 class="section-title">Our Location</h2>
    <p class="section-sub">Visit us at our clinic. We're conveniently located and easy to find.</p>

    <div class="location-inner">
        <div class="location-details">
            <div class="location-card">
                <div class="location-card-icon"><i class='bx bxs-map-pin'></i></div>
                <div>
                    <h5>Clinic Address</h5>
                    <p>Barangay Dayap, Calauan,<br>Laguna 4012, Philippines</p>
                </div>
            </div>
            <div class="location-card">
                <div class="location-card-icon"><i class='bx bxs-bus'></i></div>
                <div>
                    <h5>How to Get There</h5>
                    <p>From Santa Cruz, take a jeepney or bus bound for Calauan. Alight at Calauan town proper and take a tricycle to Barangay Dayap.</p>
                </div>
            </div>
            <div class="location-card">
                <div class="location-card-icon"><i class='bx bxs-parking'></i></div>
                <div>
                    <h5>Parking Available</h5>
                    <p>Free parking available inside the clinic compound for patients and visitors.</p>
                </div>
            </div>
            <div class="location-card">
                <div class="location-card-icon"><i class='bx bxs-time'></i></div>
                <div>
                    <h5>Operating Hours</h5>
                    <p>Mon–Fri: 9:00 AM – 5:00 PM<br>Saturday: 9:00 AM – 12:00 PM<br>Sunday: Closed</p>
                </div>
            </div>
        </div>

        <div class="map-wrap">
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3868.1!2d121.3167!3d14.1500!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd5b2e2e2e2e2f%3A0x0!2sBrgy.+Dayap%2C+Calauan%2C+Laguna!5e0!3m2!1sen!2sph!4v1"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </div>
</section>

<!-- ===== CTA ===== -->
<div class="cta">
    <h2>Ready to Get Started?</h2>
    <p>Join the clinic management system trusted by healthcare professionals.</p>
    <a href="{{ route('register') }}" class="btn-cta">
        Create Your Account <i class='bx bx-right-arrow-alt'></i>
    </a>
</div>

<!-- ===== FOOTER ===== -->
<footer>
    <p>&copy; {{ date('Y') }} Clinic Record System &mdash; Developed by <span>Benz</span></p>
</footer>

<!-- ===== VIORA CHATBOT ===== -->
<button class="viora-trigger" id="vioraTrigger" aria-label="Chat with Viora">
    <i class='bx bx-bot' id="vioraIcon"></i>
    <span class="viora-badge">1</span>
</button>

<div class="viora-window" id="vioraWindow">
    <div class="viora-header">
        <div class="viora-avatar"><i class='bx bxs-bot'></i></div>
        <div class="viora-header-info">
            <h4>Viora</h4>
            <p>ClinicRMS AI Assistant • Online</p>
        </div>
        <button class="viora-close" id="vioraClose"><i class='bx bx-x'></i></button>
    </div>

    <div class="viora-messages" id="vioraMessages">
        <!-- Messages inserted by JS -->
    </div>

    <div class="viora-quick-btns" id="vioraQuickBtns">
        <button class="quick-btn" data-q="How do I book an appointment?">📅 Book Appointment</button>
        <button class="quick-btn" data-q="What are the clinic hours?">🕐 Clinic Hours</button>
        <button class="quick-btn" data-q="How do I register?">📝 Register</button>
        <button class="quick-btn" data-q="I forgot my password.">🔑 Password Help</button>
        <button class="quick-btn" data-q="Where is the clinic located?">📍 Location</button>
        <button class="quick-btn" data-q="Is my data safe?">🔒 Privacy</button>
    </div>

    <div class="viora-input-area">
        <input class="viora-input" id="vioraInput" type="text" placeholder="Type your question here..." autocomplete="off">
        <button class="viora-send" id="vioraSend"><i class='bx bx-send'></i></button>
    </div>
</div>

<script>
// ===== FAQ ACCORDION =====
document.querySelectorAll('.faq-cat-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.faq-cat-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.faq-group').forEach(g => g.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('faq-' + btn.dataset.cat).classList.add('active');
    });
});

document.querySelectorAll('.faq-question').forEach(btn => {
    btn.addEventListener('click', () => {
        const item = btn.parentElement;
        const isOpen = item.classList.contains('open');
        // Close all in same group
        btn.closest('.faq-group').querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
        if (!isOpen) item.classList.add('open');
    });
});

// ===== VIORA CHATBOT =====
const vioraTrigger = document.getElementById('vioraTrigger');
const vioraWindow  = document.getElementById('vioraWindow');
const vioraClose   = document.getElementById('vioraClose');
const vioraMessages= document.getElementById('vioraMessages');
const vioraInput   = document.getElementById('vioraInput');
const vioraSend    = document.getElementById('vioraSend');
const vioraIcon    = document.getElementById('vioraIcon');
const vioraBadge   = vioraTrigger.querySelector('.viora-badge');
let chatOpen = false;
let greeted  = false;

const responses = [
    {
        keys: ['book','appointment','schedule','reschedule'],
        answer: `To book an appointment:\n1️⃣ Log in to your account\n2️⃣ Go to the "Appointments" section\n3️⃣ Select your preferred doctor, date, and time\n4️⃣ Submit your request\n\nYou'll receive a notification within 24–48 hours once it's approved! 😊`
    },
    {
        keys: ['hours','schedule','open','closed','operating','clinic hours'],
        answer: `🕐 Our clinic hours are:\n\n• **Monday–Friday:** 9:00 AM – 5:00 PM\n• **Saturday:** 9:00 AM – 12:00 PM\n• **Sunday:** Closed\n\nWe recommend booking an appointment before visiting! 📅`
    },
    {
        keys: ['register','how to','create account','sign up'],
        answer: `Registering for ClinicRMS is easy! 🎉\n\n1️⃣ Click the "Register" button at the top\n2️⃣ Fill in your name, email, and password\n3️⃣ Verify your email address\n4️⃣ Done! You can now log in\n\nRegistration as a patient is completely free! ✅`
    },
    {
        keys: ['password','forgot','reset','can\'t log in','login','account'],
        answer: `Having trouble logging in? Don't worry! 😊\n\n• **Forgot password:** Click "Forgot Password" on the login page and we'll send a reset link to your email.\n• **Email not verified:** Check your inbox or spam folder for the verification email.\n• **Other issues:** Contact us at support@clinicrms.com 📧`
    },
    {
        keys: ['location','where','address','map','directions','calauan','laguna','dayap'],
        answer: `📍 Our clinic is located at:\n\n**Barangay Dayap, Calauan, Laguna 4012, Philippines**\n\n🚌 How to get here:\nFrom Santa Cruz, take a jeepney or bus to Calauan. From the town proper, take a tricycle to Brgy. Dayap.\n\n🅿️ Free parking is also available for patients!`
    },
    {
        keys: ['privacy','safe','data','security','information','personal','medical','records'],
        answer: `🔒 Your privacy is our priority!\n\n• We use **encrypted connections (HTTPS)**\n• **Role-based access** — only your doctor and authorized staff can view your records\n• We never share your data with third parties\n• You can request data deletion at any time\n\nYour information is in good hands! 💜`
    },
    {
        keys: ['doctor','specialist','available','who'],
        answer: `👨‍⚕️ We have general practitioners and various specialists available.\n\nFor the most up-to-date list of our doctors:\n• Log in to your account\n• Visit the "Doctors" section\n\nOr contact us at:\n📞 +63 917 123 4567\n📧 support@clinicrms.com`
    },
    {
        keys: ['cancel','change','reschedule','modify'],
        answer: `Yes, you can cancel or reschedule an appointment! 📅\n\n1️⃣ Log in to your account\n2️⃣ Go to "My Appointments"\n3️⃣ Find the appointment and click Cancel/Reschedule\n\nⓘ We recommend doing this at least **24 hours before** your appointment. Thank you for your understanding! 🙏`
    },
    {
        keys: ['contact','reach','number','email','phone','support'],
        answer: `📞 Here is our contact information:\n\n• **Phone:** +63 917 123 4567 / +63 2 8123 4567\n• **Email:** support@clinicrms.com\n• **Address:** Brgy. Dayap, Calauan, Laguna\n\nYou can also fill out the contact form in the Contact section of the website! 😊`
    },
    {
        keys: ['hello','hi','hey','good morning','good afternoon','good evening','greetings'],
        answer: `Hello! 👋 I'm **Viora**, your ClinicRMS AI Assistant!\n\nI'm here to help you with:\n• 📅 Booking appointments\n• 🔑 Account and login issues\n• 🕐 Clinic hours and location\n• 🔒 Privacy and security\n• And much more!\n\nHow can I assist you today? 💜`
    },
    {
        keys: ['thank','thanks','okay','alright','got it','great'],
        answer: `You're welcome! 😊 I'm always here to help. If you have any more questions about ClinicRMS, feel free to ask anytime! 💜\n\nHave a great day! ☀️`
    }
];

function getResponse(msg) {
    const lower = msg.toLowerCase();
    for (const r of responses) {
        if (r.keys.some(k => lower.includes(k))) return r.answer;
    }
    return `Sorry, I'm not able to answer that question directly just yet. 😅\n\nYou can try:\n• One of the quick reply buttons below\n• Emailing us at **support@clinicrms.com**\n• Calling us at **+63 917 123 4567**\n\nIs there anything else I can help you with? 💜`;
}

function addMessage(text, type) {
    const div = document.createElement('div');
    div.className = `msg ${type}`;
    const formatted = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
    if (type === 'bot') {
        div.innerHTML = `<div class="msg-bot-avatar"><i class='bx bxs-bot'></i></div><div class="msg-bubble">${formatted}</div>`;
    } else {
        div.innerHTML = `<div class="msg-bubble">${formatted}</div>`;
    }
    vioraMessages.appendChild(div);
    vioraMessages.scrollTop = vioraMessages.scrollHeight;
}

function showTyping() {
    const div = document.createElement('div');
    div.className = 'msg bot'; div.id = 'vioraTyping';
    div.innerHTML = `<div class="msg-bot-avatar"><i class='bx bxs-bot'></i></div><div class="msg-bubble"><div class="typing-indicator"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span></div></div>`;
    vioraMessages.appendChild(div);
    vioraMessages.scrollTop = vioraMessages.scrollHeight;
}

function removeTyping() {
    const t = document.getElementById('vioraTyping');
    if (t) t.remove();
}

function sendMessage(text) {
    if (!text.trim()) return;
    addMessage(text, 'user');
    vioraInput.value = '';
    showTyping();
    setTimeout(() => {
        removeTyping();
        addMessage(getResponse(text), 'bot');
    }, 900 + Math.random() * 500);
}

vioraTrigger.addEventListener('click', () => {
    chatOpen = !chatOpen;
    vioraWindow.classList.toggle('open', chatOpen);
    vioraIcon.className = chatOpen ? 'bx bx-x' : 'bx bx-bot';
    vioraBadge.style.display = 'none';
    if (chatOpen && !greeted) {
        greeted = true;
        setTimeout(() => {
            addMessage('Hello! 👋 I\'m **Viora**, your ClinicRMS AI Assistant. How can I help you today? 💜', 'bot');
        }, 400);
    }
});

vioraClose.addEventListener('click', () => {
    chatOpen = false;
    vioraWindow.classList.remove('open');
    vioraIcon.className = 'bx bx-bot';
});

vioraSend.addEventListener('click', () => sendMessage(vioraInput.value));
vioraInput.addEventListener('keydown', e => { if (e.key === 'Enter') sendMessage(vioraInput.value); });

document.querySelectorAll('.quick-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        if (!chatOpen) {
            chatOpen = true;
            vioraWindow.classList.add('open');
            vioraIcon.className = 'bx bx-x';
            greeted = true;
        }
        sendMessage(btn.dataset.q);
    });
});
</script>

</body>
</html>