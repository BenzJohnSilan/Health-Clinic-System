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

<a class="skip-link" href="#main-content">Skip to content</a>

<!-- ===== NAVBAR ===== -->
<nav aria-label="Main navigation">
    <button class="nav-toggle" id="navToggle" type="button" aria-expanded="false" aria-controls="primaryNavigation" aria-label="Open navigation menu">
        <i class='bx bx-menu'></i>
    </button>

    <a class="nav-brand" href="#home">
        <i class='bx bx-plus-medical' style="font-size:18px; vertical-align:middle;"></i>
        ClinicRMS
    </a>

    <ul class="nav-center" id="primaryNavigation">
        <li><a href="#home">Home</a></li>
        <li><a href="#about">About Us</a></li>
        <li><a href="#services">Services</a></li>
        <li><a href="#how-it-works">How It Works</a></li>
        <li><a href="#contact">Contact</a></li>
    </ul>

    <div class="nav-links">
        <a href="{{ route('login') }}" class="btn-nav-outline">Login</a>
        <a href="{{ route('register') }}" class="btn-nav-fill">Register</a>
    </div>

</nav>

<main id="main-content">
<!-- ===== HERO ===== -->
<div id="home" class="hero">
    <div class="hero-grid">
        <div class="hero-content">
            <div class="hero-badge">
                <i class='bx bxs-shield-plus'></i>
                Secure Patient Portal
            </div>

            <h1>
                Your Health,<br>
                <span>Our Priority</span>
            </h1>

            <p>
                Manage your appointments and access your medical records anytime
                through our clinic's secure, easy-to-use online patient portal.
            </p>

            <div class="hero-buttons">
                <a href="{{ route('register') }}" class="btn-primary-hero">
                    Get Started <i class='bx bx-right-arrow-alt'></i>
                </a>
                <a href="#about" class="btn-secondary-hero">
                    Learn More
                </a>
            </div>
        </div>

        <div class="hero-visual">
            <div class="portal-card">
                <div class="portal-card-header">
                    <div class="portal-dots"><span></span><span></span><span></span></div>
                    <span class="portal-card-title"><i class='bx bxs-shield-plus'></i> Patient Portal</span>
                </div>

                <div class="portal-row">
                    <div class="portal-row-icon"><i class='bx bxs-calendar-check'></i></div>
                    <div class="portal-row-text">
                        <h6>Appointments</h6>
                        <p>General Consultation</p>
                    </div>
                    <span class="portal-status status-approved">Approved</span>
                </div>

                <div class="portal-row">
                    <div class="portal-row-icon"><i class='bx bxs-folder-open'></i></div>
                    <div class="portal-row-text">
                        <h6>Medical Records</h6>
                        <p>Securely stored & organized</p>
                    </div>
                    <span class="portal-status status-neutral">View</span>
                </div>

                <div class="portal-row">
                    <div class="portal-row-icon"><i class='bx bxs-lock-alt'></i></div>
                    <div class="portal-row-text">
                        <h6>Secure Access</h6>
                        <p>Role-based & encrypted</p>
                    </div>
                    <span class="portal-status status-secure"><i class='bx bx-check-shield'></i></span>
                </div>
            </div>

            <div class="hero-visual-badge"><i class='bx bxs-badge-check'></i> Private &amp; Secure</div>
        </div>
    </div>
</div>

<!-- ===== ABOUT ===== -->
<section id="about">
    <div class="about-inner">
        <div class="about-left reveal">
            <p class="section-label" style="text-align:left;">Who We Are</p>
            <h2>About Our <span>Clinic</span></h2>
            <p>
                Our clinic is committed to providing accessible and organized healthcare
                services for the community. Through our online patient portal, patients
                can manage appointments and access their personal health records in one
                convenient platform.
            </p>
            <p>
                Whether you're a patient booking your first appointment or a member of
                our clinical team, our system provides a smooth and secure experience
                tailored to your role — with access strictly limited to what's relevant
                to you.
            </p>
        </div>

        <div class="about-right reveal">
            <div class="about-img-wrap">
                <i class='bx bxs-clinic'></i>
            </div>
        </div>
    </div>
</section>

<!-- ===== SERVICES ===== -->
<section id="services">
    <p class="section-label">What We Offer</p>
    <h2 class="section-title">Our Services</h2>
    <p class="section-sub">Straightforward, well-organized care — supported by our online patient portal.</p>

    <div class="services-grid">
        <div class="service-card reveal">
            <div class="service-icon"><i class='bx bx-plus-medical'></i></div>
            <h4>General Consultation</h4>
            <p>Sit down with a doctor to discuss your health concerns and get the guidance you need.</p>
        </div>
        <div class="service-card reveal">
            <div class="service-icon"><i class='bx bxs-heart'></i></div>
            <h4>Health Checkup</h4>
            <p>Routine checkups to help keep track of your health and catch concerns early.</p>
        </div>
        <div class="service-card reveal">
            <div class="service-icon"><i class='bx bxs-calendar-plus'></i></div>
            <h4>Appointment Scheduling</h4>
            <p>Request a visit online and get notified as soon as the clinic confirms your slot.</p>
        </div>
        <div class="service-card reveal">
            <div class="service-icon"><i class='bx bxs-report'></i></div>
            <h4>Medical Records</h4>
            <p>Your visit history and reports, kept organized and accessible whenever you need them.</p>
        </div>
    </div>
</section>

<!-- ===== PATIENT PORTAL FEATURES ===== -->
<section id="portal-features">
    <p class="section-label">Your Account</p>
    <h2 class="section-title">What You Can Do In The Patient Portal</h2>
    <p class="section-sub">Once your account is approved, everything you need is a login away.</p>

    <div class="portal-features-grid">
        <div class="portal-feature reveal">
            <div class="portal-feature-icon"><i class='bx bxs-calendar-week'></i></div>
            <div>
                <h4>Manage Appointments</h4>
                <p>Request, track, and reschedule your appointments from your dashboard.</p>
            </div>
        </div>
        <div class="portal-feature reveal">
            <div class="portal-feature-icon"><i class='bx bxs-file-find'></i></div>
            <div>
                <h4>View Medical Records</h4>
                <p>Access your own reports and visit history whenever you need them.</p>
            </div>
        </div>
        <div class="portal-feature reveal">
            <div class="portal-feature-icon"><i class='bx bxs-id-card'></i></div>
            <div>
                <h4>Access Personal Information</h4>
                <p>Review and update your profile and contact details securely.</p>
            </div>
        </div>
        <div class="portal-feature reveal">
            <div class="portal-feature-icon"><i class='bx bxs-bell-ring'></i></div>
            <div>
                <h4>Receive Clinic Updates</h4>
                <p>Get organized, real-time notifications about your appointment status.</p>
            </div>
        </div>
    </div>
</section>

<!-- ===== HOW IT WORKS ===== -->
<section id="how-it-works">
    <p class="section-label">Getting Started</p>
    <h2 class="section-title">How It Works</h2>
    <p class="section-sub">Getting access to your patient account takes just a few simple steps.</p>

    <div class="steps-row">
        <div class="step-item reveal">
            <div class="step-icon-wrap">
                <div class="step-icon"><i class='bx bxs-user-plus'></i></div>
                <span class="step-num-badge">01</span>
            </div>
            <h4>Register an Account</h4>
            <p>Create your patient account with your basic information in just a few minutes.</p>
        </div>
        <div class="step-item reveal">
            <div class="step-icon-wrap">
                <div class="step-icon"><i class='bx bxs-user-check'></i></div>
                <span class="step-num-badge">02</span>
            </div>
            <h4>Wait for Clinic Approval</h4>
            <p>Our clinic staff reviews new patient accounts to keep records secure and accurate.</p>
        </div>
        <div class="step-item reveal">
            <div class="step-icon-wrap">
                <div class="step-icon"><i class='bx bxs-calendar-check'></i></div>
                <span class="step-num-badge">03</span>
            </div>
            <h4>Book an Appointment</h4>
            <p>Once approved, log in anytime to request an appointment for your preferred date.</p>
        </div>
        <div class="step-item reveal">
            <div class="step-icon-wrap">
                <div class="step-icon"><i class='bx bxs-institution'></i></div>
                <span class="step-num-badge">04</span>
            </div>
            <h4>Visit the Clinic</h4>
            <p>Arrive for your scheduled visit and let our clinic team take care of the rest.</p>
        </div>
    </div>
</section>

<!-- ===== SYSTEM WORKFLOW ===== -->
<section id="workflow">
    <p class="section-label">Behind The Scenes</p>
    <h2 class="section-title">How Your Appointment Is Handled</h2>
    <p class="section-sub">A simple, transparent workflow from request to completed visit.</p>

    <div class="workflow-track">
        <div class="workflow-step reveal">
            <div class="workflow-icon"><i class='bx bx-message-square-detail'></i></div>
            <span>Appointment Request</span>
        </div>
        <div class="workflow-step reveal">
            <div class="workflow-icon"><i class='bx bx-check-double'></i></div>
            <span>Approval</span>
        </div>
        <div class="workflow-step reveal">
            <div class="workflow-icon"><i class='bx bx-log-in-circle'></i></div>
            <span>Checked In</span>
        </div>
        <div class="workflow-step reveal">
            <div class="workflow-icon"><i class='bx bx-plus-medical'></i></div>
            <span>Consultation</span>
        </div>
        <div class="workflow-step reveal">
            <div class="workflow-icon"><i class='bx bx-badge-check'></i></div>
            <span>Completed</span>
        </div>
    </div>
</section>

<!-- ===== FAQs SECTION ===== -->
<section id="faqs">
    <p class="section-label">Got Questions?</p>
    <h2 class="section-title">Frequently Asked Questions</h2>
    <p class="section-sub">Find answers to the most common questions about our clinic system. Can't find what you're looking for? Chat with Viora!</p>

    <div class="faqs-inner reveal">
        <!-- Category Tabs -->
        <div class="faq-categories">
            <button class="faq-cat-btn active" data-cat="appointments">Appointments</button>
            <button class="faq-cat-btn" data-cat="account">Account & Login</button>
            <button class="faq-cat-btn" data-cat="records">Medical Records</button>
            <button class="faq-cat-btn" data-cat="clinic">Clinic Info</button>
        </div>

        <!-- Appointments -->
        <div class="faq-group active" id="faq-appointments">
            <div class="faq-item">
                <button class="faq-question">
                    How do I book an appointment?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Log in to your account, go to the <strong>Appointments</strong> section, select your preferred doctor, date, and time, then submit your booking request. You will receive a notification once your appointment has been approved.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    Can I reschedule or cancel my appointment?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Yes. Go to <strong>My Appointments</strong> in your dashboard and select the appointment you wish to cancel or reschedule. We recommend doing so at least <strong>24 hours before</strong> your scheduled time to allow other patients to book that slot.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    How long does it take for an appointment to be confirmed?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Appointments are typically reviewed and confirmed within <strong>24 to 48 hours</strong> during clinic operating hours. You will receive a system notification whenever the status of your booking changes.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    What happens if I miss my scheduled appointment?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>If you miss your appointment, you may request a new schedule depending on available clinic slots. Repeated missed appointments without prior notice may affect future booking priority.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    Can I walk in without an appointment?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Walk-in patients may still be accommodated depending on clinic availability and queue capacity. However, booking in advance is strongly recommended to secure your preferred time slot.</p></div>
            </div>
        </div>

        <!-- Account & Login -->
        <div class="faq-group" id="faq-account">
            <div class="faq-item">
                <button class="faq-question">
                    How do I register for an account?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Click the <strong>"Register"</strong> button in the navigation bar. Fill in your name, email address, and password. Verify your email through the link we send you, and you're ready to use the system. Registration as a patient is completely free.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    I forgot my password. What should I do?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>On the login page, click <strong>"Forgot Password"</strong> and enter your registered email address. We will send you a link to reset your password. Check your spam or junk folder if you don't see the email in your inbox.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    How do I update my personal information?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Go to <strong>Profile Settings</strong> after logging in. From there, you can edit your name, contact number, and other personal details. Make sure to save your changes when done.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    Who can access and use ClinicRMS?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>The system supports three types of users: <strong>Admins</strong> who manage clinic operations, <strong>Doctors</strong> who access their assigned patients and schedules, and <strong>Patients</strong> who can book appointments and view their own records. Each role has controlled access to relevant data only.</p></div>
            </div>
        </div>

        <!-- Medical Records -->
        <div class="faq-group" id="faq-records">
            <div class="faq-item">
                <button class="faq-question">
                    Can I view my medical history and past consultations?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Yes. In your patient dashboard, you can view your appointment history and medical reports issued by your doctor. Access to other patients' records is strictly restricted to protect privacy.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    Will my medical records and personal information remain confidential?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Yes. We use <strong>encrypted connections (HTTPS)</strong> and role-based access controls to ensure only authorized personnel can view your records. We never share your personal or medical data with third parties.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    Can I request a copy of my medical record?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Yes. Requests for medical records may be processed following clinic policies and proper patient verification procedures. Please contact the clinic staff or email us at <strong>support@clinicrms.com</strong> to initiate your request.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    What should I do if there is incorrect information in my record?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Inform the clinic staff immediately so the necessary corrections can be made. You may also reach us at <strong>support@clinicrms.com</strong> or call <strong>+63 917 123 4567</strong> for assistance.</p></div>
            </div>
        </div>

        <!-- Clinic Info -->
        <div class="faq-group" id="faq-clinic">
            <div class="faq-item">
                <button class="faq-question">
                    What are the clinic's operating hours?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>The clinic is open <strong>Monday through Friday, 9:00 AM – 5:00 PM</strong> and <strong>Saturday, 9:00 AM – 12:00 PM</strong>. We are closed on Sundays and public holidays.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    What doctors and specialists are available?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>We have general practitioners and various specialists on staff. For the most up-to-date list, log in to your account and visit the <strong>Doctors</strong> section, or contact the clinic directly at <strong>+63 917 123 4567</strong>.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    Is the system accessible on mobile phones?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>Yes. ClinicRMS is fully responsive and works on all devices — desktop, tablet, or smartphone — with an internet connection. No app installation is required.</p></div>
            </div>
            <div class="faq-item">
                <button class="faq-question">
                    Who should I contact for concerns or technical assistance?
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>You may contact the clinic staff directly, email us at <strong>support@clinicrms.com</strong>, or call <strong>+63 917 123 4567</strong>. You can also use the Contact form on this page or chat with our AI assistant <strong>Viora</strong> for quick answers.</p></div>
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
        <div class="contact-info reveal">
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
        </div>

        <div class="contact-form reveal">
            <div class="form-row">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" placeholder="First name">
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" placeholder="Last name">
                </div>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" placeholder="you@email.com">
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

<!-- ===== LOCATION / CLINIC INFO ===== -->
<section id="location">
    <p class="section-label">Find Us</p>
    <h2 class="section-title">Clinic Information</h2>
    <p class="section-sub">Visit us at our clinic. We're conveniently located and easy to find.</p>

    <div class="location-inner">
        <div class="location-details reveal">
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

        <div class="map-wrap reveal">
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3868.1!2d121.3167!3d14.1500!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd5b2e2e2e2e2f%3A0x0!2sBrgy.+Dayap%2C+Calauan%2C+Laguna!5e0!3m2!1sen!2sph!4v1"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </div>
</section>
</main>

<!-- ===== FOOTER ===== -->
<footer>
    <div class="footer-inner">
        <div class="footer-col footer-brand">
            <a class="footer-logo" href="#home">
                <i class='bx bx-plus-medical'></i> ClinicRMS
            </a>
            <p>Our clinic's official online patient portal for managing appointments and medical records, securely and in one place.</p>
        </div>

        <div class="footer-col">
            <h5>Quick Links</h5>
            <ul>
                <li><a href="#home">Home</a></li>
                <li><a href="#about">About Us</a></li>
                <li><a href="#services">Services</a></li>
                <li><a href="#how-it-works">How It Works</a></li>
                <li><a href="#faqs">FAQs</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h5>Contact</h5>
            <ul class="footer-contact">
                <li><i class='bx bxs-map'></i> Barangay Dayap, Calauan, Laguna 4012, Philippines</li>
                <li><i class='bx bxs-phone-call'></i> +63 917 123 4567</li>
                <li><i class='bx bxs-envelope'></i> support@clinicrms.com</li>
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; {{ date('Y') }} Clinic Record System. All rights reserved.</p>
        <div class="footer-legal">
            <a href="#">Privacy Policy</a>
            <span>·</span>
            <a href="#">Terms of Use</a>
        </div>
    </div>
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
        <button class="viora-close" id="vioraClose" type="button" aria-label="Close chat"><i class='bx bx-x'></i></button>
    </div>

    <div class="viora-messages" id="vioraMessages"></div>

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
        <button class="viora-send" id="vioraSend" type="button" aria-label="Send message"><i class='bx bx-send'></i></button>
    </div>
</div>

<script>
// ===== SCROLL REVEAL =====
const navToggle = document.getElementById('navToggle');
const primaryNavigation = document.getElementById('primaryNavigation');

navToggle.addEventListener('click', () => {
    const isOpen = primaryNavigation.classList.toggle('is-open');
    navToggle.setAttribute('aria-expanded', isOpen);
    navToggle.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
    navToggle.innerHTML = `<i class='bx ${isOpen ? 'bx-x' : 'bx-menu'}'></i>`;
});

primaryNavigation.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
    primaryNavigation.classList.remove('is-open');
    navToggle.setAttribute('aria-expanded', 'false');
    navToggle.setAttribute('aria-label', 'Open navigation menu');
    navToggle.innerHTML = "<i class='bx bx-menu'></i>";
}));

document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && primaryNavigation.classList.contains('is-open')) {
        navToggle.click();
        navToggle.focus();
    }
});

const revealEls = document.querySelectorAll('.reveal');
if ('IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -60px 0px' });
    revealEls.forEach(el => revealObserver.observe(el));
} else {
    revealEls.forEach(el => el.classList.add('is-visible'));
}

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
        btn.closest('.faq-group').querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
        if (!isOpen) item.classList.add('open');
    });
});

// ===== VIORA CHATBOT =====
const vioraTrigger  = document.getElementById('vioraTrigger');
const vioraWindow   = document.getElementById('vioraWindow');
const vioraClose    = document.getElementById('vioraClose');
const vioraMessages = document.getElementById('vioraMessages');
const vioraInput    = document.getElementById('vioraInput');
const vioraSend     = document.getElementById('vioraSend');
const vioraIcon     = document.getElementById('vioraIcon');
const vioraBadge    = vioraTrigger.querySelector('.viora-badge');
let chatOpen = false;
let greeted  = false;

const responses = [
    {
        keys: ['book', 'appointment', 'schedule'],
        answer: `To book an appointment:\n1️⃣ Log in to your account\n2️⃣ Go to the "Appointments" section\n3️⃣ Select your preferred doctor, date, and time\n4️⃣ Submit your request\n\nYou'll receive a notification within 24–48 hours once it's approved! 😊`
    },
    {
        keys: ['reschedule', 'cancel', 'change appointment'],
        answer: `Yes, you can cancel or reschedule an appointment! 📅\n\n1️⃣ Log in to your account\n2️⃣ Go to "My Appointments"\n3️⃣ Select the appointment and choose Cancel or Reschedule\n\nⓘ We recommend doing this at least **24 hours before** your appointment. 🙏`
    },
    {
        keys: ['confirm', 'approved', 'how long', 'notification'],
        answer: `Appointments are usually reviewed and confirmed within **24 to 48 hours** ⏱️ during clinic operating hours.\n\nYou will receive a system notification as soon as the status of your booking changes!`
    },
    {
        keys: ['walk in', 'walk-in', 'without appointment', 'no appointment'],
        answer: `Walk-in patients may still be accommodated depending on clinic availability and queue capacity. 🚶\n\nHowever, we strongly recommend **booking in advance** to secure your preferred time slot!`
    },
    {
        keys: ['miss', 'missed', 'absent', 'no show'],
        answer: `If you miss your appointment, you may request a new schedule depending on available clinic slots. 📅\n\nPlease make sure to cancel in advance if you're unable to attend so others can use that slot. 🙏`
    },
    {
        keys: ['hours', 'open', 'closed', 'operating', 'clinic hours', 'schedule'],
        answer: `🕐 Our clinic hours are:\n\n• **Monday–Friday:** 9:00 AM – 5:00 PM\n• **Saturday:** 9:00 AM – 12:00 PM\n• **Sunday:** Closed\n\nWe recommend booking an appointment before visiting! 📅`
    },
    {
        keys: ['register', 'sign up', 'create account'],
        answer: `Registering for ClinicRMS is easy and free! 🎉\n\n1️⃣ Click the "Register" button at the top\n2️⃣ Fill in your name, email, and password\n3️⃣ Verify your email address\n4️⃣ Done! You're ready to use the system ✅`
    },
    {
        keys: ['password', 'forgot', 'reset', "can't log in", 'login issue'],
        answer: `Having trouble logging in? Don't worry! 😊\n\n• **Forgot password:** Click "Forgot Password" on the login page and we'll send a reset link to your email.\n• **Can't log in:** Make sure your email has been verified. Check your inbox or spam folder.\n• **Still stuck?** Contact us at support@clinicrms.com 📧`
    },
    {
        keys: ['update', 'personal info', 'profile', 'edit account'],
        answer: `To update your personal information:\n\n1️⃣ Log in to your account\n2️⃣ Go to **Profile Settings**\n3️⃣ Edit your name, contact number, or other details\n4️⃣ Save your changes ✅`
    },
    {
        keys: ['medical record', 'history', 'consultation', 'past visit', 'records'],
        answer: `Yes! In your patient dashboard, you can view:\n\n• 📋 Your appointment history\n• 📄 Medical reports from your doctor\n• 👤 Your personal information\n\nAccess to other patients' records is strictly restricted. 🔒`
    },
    {
        keys: ['copy', 'request record', 'get record'],
        answer: `Yes, you can request a copy of your medical record! 📄\n\nRequests are processed following clinic policies and patient verification procedures.\n\nPlease contact us at:\n📧 support@clinicrms.com\n📞 +63 917 123 4567`
    },
    {
        keys: ['wrong', 'incorrect', 'error in record', 'fix record'],
        answer: `If there's incorrect information in your record, please inform the clinic staff immediately so corrections can be made. 📝\n\nYou can also reach us at:\n📧 support@clinicrms.com\n📞 +63 917 123 4567`
    },
    {
        keys: ['privacy', 'safe', 'data', 'security', 'confidential', 'information'],
        answer: `🔒 Your privacy is our priority!\n\n• **Encrypted connections (HTTPS)** protect your data\n• **Role-based access** — only your doctor and authorized staff can view your records\n• We never share your data with third parties\n\nYour information is in good hands! 💜`
    },
    {
        keys: ['doctor', 'specialist', 'available doctor'],
        answer: `👨‍⚕️ We have general practitioners and various specialists available.\n\nFor the most up-to-date list:\n• Log in and visit the **Doctors** section\n• Or contact us at:\n📞 +63 917 123 4567\n📧 support@clinicrms.com`
    },
    {
        keys: ['location', 'where', 'address', 'map', 'directions', 'calauan', 'laguna', 'dayap'],
        answer: `📍 Our clinic is located at:\n\n**Barangay Dayap, Calauan, Laguna 4012, Philippines**\n\n🚌 **How to get here:**\nFrom Santa Cruz, take a jeepney or bus to Calauan. From the town proper, take a tricycle to Brgy. Dayap.\n\n🅿️ Free parking is available for patients!`
    },
    {
        keys: ['mobile', 'phone', 'app', 'smartphone', 'tablet'],
        answer: `Yes! ClinicRMS is fully responsive and works on all devices — desktop, tablet, or smartphone. 📱\n\nNo app installation is required. Just open the site in your browser and you're good to go! ✅`
    },
    {
        keys: ['contact', 'reach', 'number', 'email', 'phone', 'support'],
        answer: `📞 Here is our contact information:\n\n• **Phone:** +63 917 123 4567 / +63 2 8123 4567\n• **Email:** support@clinicrms.com\n• **Address:** Brgy. Dayap, Calauan, Laguna\n\nYou can also fill out the contact form on this page! 😊`
    },
    {
        keys: ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening', 'greetings'],
        answer: `Hello! 👋 I'm **Viora**, your ClinicRMS AI Assistant!\n\nI'm here to help you with:\n• 📅 Booking & managing appointments\n• 🔑 Account and login issues\n• 🕐 Clinic hours and location\n• 🔒 Privacy and medical records\n• And much more!\n\nHow can I assist you today? 💜`
    },
    {
        keys: ['thank', 'thanks', 'okay', 'alright', 'got it', 'great'],
        answer: `You're welcome! 😊 If you have any more questions about ClinicRMS, feel free to ask anytime!\n\nHave a great day! ☀️`
    }
];

function getResponse(msg) {
    const lower = msg.toLowerCase();
    for (const r of responses) {
        if (r.keys.some(k => lower.includes(k))) return r.answer;
    }
    return `Sorry, I'm not able to answer that directly just yet. 😅\n\nYou can try:\n• One of the quick reply buttons below\n• Emailing us at **support@clinicrms.com**\n• Calling us at **+63 917 123 4567**\n\nIs there anything else I can help you with? 💜`;
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
