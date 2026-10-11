<!DOCTYPE html>
@use('App\Support\LandingText', 'T')
@php
    // $settings / $items come from LandingController (Admin → Website Management,
    // with the original text as fallback). All text is escaped; see App\Support\LandingText.
    $s = $settings;
    $url = fn ($u, $default) => ($u !== '' && $u !== null && T::isSafeUrl($u)) ? $u : $default;
    $heroPrimaryUrl   = $url($s['hero_primary_url'], route('register'));
    $heroSecondaryUrl = $url($s['hero_secondary_url'], '#about');
    $mapDefault = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3868.1!2d121.3167!3d14.1500!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd5b2e2e2e2e2f%3A0x0!2sBrgy.+Dayap%2C+Calauan%2C+Laguna!5e0!3m2!1sen!2sph!4v1';
    $mapUrl = preg_match('#^https://www\.google\.com/maps/embed\?[^\s"<>]+$#', (string) $s['location_map_url']) ? $s['location_map_url'] : $mapDefault;

    // Plain facts handed to the Viora chatbot script so its answers match the page.
    $phoneLines = T::lines($s['contact_phones']);
    $emailLines = T::lines($s['contact_emails']);
    $vioraInfo = [
        'brand'      => $s['brand_name'],
        'phone'      => $phoneLines[0] ?? $s['footer_phone'],
        'phones'     => implode(' / ', $phoneLines) ?: $s['footer_phone'],
        'email'      => $emailLines[0] ?? $s['footer_email'],
        'address'    => trim(preg_replace('/,\s*,/', ',', implode(', ', T::lines($s['location_address'])))),
        'hours'      => T::lines($s['location_hours']),
        'directions' => (string) $s['location_directions'],
        'parking'    => (string) $s['location_parking'],
    ];

    // FAQ tabs: only categories that have at least one visible question.
    $faqGroups = collect($items['faqs'])->groupBy('category');
    $faqTabs = collect(\App\Support\LandingDefaults::FAQ_CATEGORIES)->filter(fn ($label, $key) => $faqGroups->has($key));
    $faqFirst = $faqTabs->keys()->first();
@endphp

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
        {{ $s['brand_name'] }}
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
    <div class="hero-grid {{ $s['hero_image_url'] ? '' : 'is-text-only' }}">
        <div class="hero-content">
            @if(trim($s['hero_badge']) !== '')
            <div class="hero-badge">
                <i class='bx bxs-shield-plus'></i>
                {{ $s['hero_badge'] }}
            </div>
            @endif

            <h1>
                {{ $s['hero_title_line1'] }}@if(trim($s['hero_title_highlight']) !== '')<br>
                <span>{{ $s['hero_title_highlight'] }}</span>@endif
            </h1>

            <p>
                {{ $s['hero_description'] }}
            </p>

            <div class="hero-buttons">
                <a href="{{ $heroPrimaryUrl }}" class="btn-primary-hero">
                    {{ $s['hero_primary_label'] }} <i class='bx bx-right-arrow-alt'></i>
                </a>
                <a href="{{ $heroSecondaryUrl }}" class="btn-secondary-hero">
                    {{ $s['hero_secondary_label'] }}
                </a>
            </div>
        </div>

        @if($s['hero_image_url'])
        {{-- Admin hero picture: shown on its own, no card/box. Without one, the hero is text-only and centred. --}}
        <div class="hero-visual">
            <img class="hero-banner-img" src="{{ $s['hero_image_url'] }}" alt="" loading="eager">
        </div>
        @endif
    </div>
</div>

<!-- ===== QUICK INFO (built from the Contact / Clinic Information settings) ===== -->
@php
    $quickHours   = T::lines($s['location_hours'])[0] ?? '';
    $quickPhone   = $phoneLines[0] ?? trim((string) $s['footer_phone']);
    $quickAddress = $vioraInfo['address'];
    $quickTel     = preg_replace('/[^0-9+]/', '', $quickPhone);
@endphp
@if($quickHours !== '' || $quickPhone !== '' || $quickAddress !== '')
<section class="quick-info" aria-label="Clinic at a glance">
    <div class="quick-info-inner">
        @if($quickHours !== '')
        <div class="quick-item">
            <span class="quick-icon"><i class='bx bx-time-five'></i></span>
            <div><small>Clinic hours</small><strong>{{ $quickHours }}</strong></div>
        </div>
        @endif
        @if($quickPhone !== '')
        <a class="quick-item" @if($quickTel !== '') href="tel:{{ $quickTel }}" @endif>
            <span class="quick-icon"><i class='bx bx-phone'></i></span>
            <div><small>Call us</small><strong>{{ $quickPhone }}</strong></div>
        </a>
        @endif
        @if($quickAddress !== '')
        <a class="quick-item" href="#location">
            <span class="quick-icon"><i class='bx bx-map'></i></span>
            <div><small>Visit us</small><strong>{{ $quickAddress }}</strong></div>
        </a>
        @endif
    </div>
</section>
@endif

<!-- ===== ABOUT ===== -->
<section id="about">
    <div class="about-inner">
        <div class="about-left reveal">
            <p class="section-label" style="text-align:left;">{{ $s['about_label'] }}</p>
            <h2>{{ $s['about_title'] }}@if(trim($s['about_title_highlight']) !== '') <span>{{ $s['about_title_highlight'] }}</span>@endif</h2>
            <p>{{ $s['about_text_1'] }}</p>
            @if(trim((string) $s['about_text_2']) !== '')
            <p>{{ $s['about_text_2'] }}</p>
            @endif
        </div>

        <div class="about-right reveal">
            @if($s['about_image_url'])
                {{-- Admin picture: shown on its own, no frame or box. --}}
                <img class="about-img-photo" src="{{ $s['about_image_url'] }}" alt="" loading="lazy">
            @else
                <div class="about-img-wrap">
                    <i class='bx bxs-clinic'></i>
                </div>
            @endif
        </div>
    </div>
</section>

@if(count($items['services']))
<!-- ===== SERVICES ===== -->
<section id="services">
    <p class="section-label">{{ $s['services_label'] }}</p>
    <h2 class="section-title">{{ $s['services_title'] }}</h2>
    <p class="section-sub">{{ $s['services_sub'] }}</p>

    <div class="services-grid">
        @foreach($items['services'] as $service)
        <div class="service-card reveal">
            <div class="service-icon">
                @if($service['image'])
                    <img class="service-photo" src="{{ $service['image'] }}" alt="" loading="lazy">
                @else
                    <i class='bx {{ $service['icon'] ?? 'bx-plus-medical' }}'></i>
                @endif
            </div>
            <h4>{{ $service['title'] }}</h4>
            <p>{{ $service['body'] }}</p>
        </div>
        @endforeach
    </div>
</section>
@endif

@if(count($items['features']))
<!-- ===== PATIENT PORTAL FEATURES ===== -->
<section id="portal-features">
    <p class="section-label">{{ $s['features_label'] }}</p>
    <h2 class="section-title">{{ $s['features_title'] }}</h2>
    <p class="section-sub">{{ $s['features_sub'] }}</p>

    <div class="portal-features-grid">
        @foreach($items['features'] as $feature)
        <div class="portal-feature reveal">
            <div class="portal-feature-icon"><i class='bx {{ $feature['icon'] ?? 'bxs-check-circle' }}'></i></div>
            <div>
                <h4>{{ $feature['title'] }}</h4>
                <p>{{ $feature['body'] }}</p>
            </div>
        </div>
        @endforeach
    </div>
</section>
@endif

@if(count($items['steps']))
<!-- ===== HOW IT WORKS ===== -->
<section id="how-it-works">
    <p class="section-label">{{ $s['steps_label'] }}</p>
    <h2 class="section-title">{{ $s['steps_title'] }}</h2>
    <p class="section-sub">{{ $s['steps_sub'] }}</p>

    <div class="steps-row">
        @foreach($items['steps'] as $step)
        <div class="step-item reveal">
            <div class="step-icon-wrap">
                <div class="step-icon"><i class='bx {{ $step['icon'] ?? 'bxs-check-circle' }}'></i></div>
                <span class="step-num-badge">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
            </div>
            <h4>{{ $step['title'] }}</h4>
            <p>{{ $step['body'] }}</p>
        </div>
        @endforeach
    </div>
</section>
@endif

@if(count($items['workflow']))
<!-- ===== SYSTEM WORKFLOW ===== -->
<section id="workflow">
    <p class="section-label">{{ $s['workflow_label'] }}</p>
    <h2 class="section-title">{{ $s['workflow_title'] }}</h2>
    <p class="section-sub">{{ $s['workflow_sub'] }}</p>

    <div class="workflow-track">
        @foreach($items['workflow'] as $flow)
        <div class="workflow-step reveal">
            <div class="workflow-icon"><i class='bx {{ $flow['icon'] ?? 'bx-check' }}'></i></div>
            <span>{{ $flow['title'] }}</span>
        </div>
        @endforeach
    </div>
</section>
@endif

@if($faqTabs->isNotEmpty())
<!-- ===== FAQs SECTION ===== -->
<section id="faqs">
    <p class="section-label">{{ $s['faqs_label'] }}</p>
    <h2 class="section-title">{{ $s['faqs_title'] }}</h2>
    <p class="section-sub">{{ $s['faqs_sub'] }}</p>

    <div class="faqs-inner reveal">
        <!-- Category Tabs -->
        <div class="faq-categories">
            @foreach($faqTabs as $catKey => $catLabel)
            <button class="faq-cat-btn {{ $catKey === $faqFirst ? 'active' : '' }}" data-cat="{{ $catKey }}">{{ $catLabel }}</button>
            @endforeach
        </div>

        @foreach($faqTabs as $catKey => $catLabel)
        <!-- {{ $catLabel }} -->
        <div class="faq-group {{ $catKey === $faqFirst ? 'active' : '' }}" id="faq-{{ $catKey }}">
            @foreach($faqGroups[$catKey] as $faq)
            <div class="faq-item">
                <button class="faq-question">
                    {{ $faq['title'] }}
                    <span class="faq-icon"><i class='bx bx-plus'></i></span>
                </button>
                <div class="faq-answer"><p>{{ T::format($faq['body']) }}</p></div>
            </div>
            @endforeach
        </div>
        @endforeach
    </div>
</section>
@endif

<!-- ===== CONTACT ===== -->
<section id="contact">
    <p class="section-label">{{ $s['contact_label'] }}</p>
    <h2 class="section-title">{{ $s['contact_title'] }}</h2>
    <p class="section-sub">{{ $s['contact_sub'] }}</p>

    <div class="contact-inner">
        <div class="contact-info reveal">
            <div class="contact-item">
                <div class="contact-icon"><i class='bx bxs-map'></i></div>
                <div>
                    <h5>Our Address</h5>
                    <p>{{ T::format($s['contact_address'], false) }}</p>
                </div>
            </div>
            <div class="contact-item">
                <div class="contact-icon"><i class='bx bxs-phone-call'></i></div>
                <div>
                    <h5>Phone Number</h5>
                    <p>{{ T::format($s['contact_phones'], false) }}</p>
                </div>
            </div>
            <div class="contact-item">
                <div class="contact-icon"><i class='bx bxs-envelope'></i></div>
                <div>
                    <h5>Email Address</h5>
                    <p>{{ T::format($s['contact_emails'], false) }}</p>
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
    <p class="section-label">{{ $s['location_label'] }}</p>
    <h2 class="section-title">{{ $s['location_title'] }}</h2>
    <p class="section-sub">{{ $s['location_sub'] }}</p>

    <div class="location-inner">
        <div class="location-details reveal">
            <div class="location-card">
                <div class="location-card-icon"><i class='bx bxs-map-pin'></i></div>
                <div>
                    <h5>Clinic Address</h5>
                    <p>{{ T::format($s['location_address'], false) }}</p>
                </div>
            </div>
            @if(trim((string) $s['location_directions']) !== '')
            <div class="location-card">
                <div class="location-card-icon"><i class='bx bxs-bus'></i></div>
                <div>
                    <h5>How to Get There</h5>
                    <p>{{ T::format($s['location_directions'], false) }}</p>
                </div>
            </div>
            @endif
            @if(trim((string) $s['location_parking']) !== '')
            <div class="location-card">
                <div class="location-card-icon"><i class='bx bxs-parking'></i></div>
                <div>
                    <h5>Parking Available</h5>
                    <p>{{ T::format($s['location_parking'], false) }}</p>
                </div>
            </div>
            @endif
            <div class="location-card">
                <div class="location-card-icon"><i class='bx bxs-time'></i></div>
                <div>
                    <h5>Operating Hours</h5>
                    <p>{{ T::format($s['location_hours'], false) }}</p>
                </div>
            </div>
        </div>

        <div class="map-wrap reveal">
            <iframe
                src="{{ $mapUrl }}"
                title="Clinic location map"
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
                <i class='bx bx-plus-medical'></i> {{ $s['brand_name'] }}
            </a>
            <p>{{ $s['footer_description'] }}</p>
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
                <li><i class='bx bxs-map'></i> {{ $s['footer_address'] }}</li>
                <li><i class='bx bxs-phone-call'></i> {{ $s['footer_phone'] }}</li>
                <li><i class='bx bxs-envelope'></i> {{ $s['footer_email'] }}</li>
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; {{ date('Y') }} {{ $s['footer_copyright_name'] }}. All rights reserved.</p>
        <div class="footer-legal">
            <a href="{{ $url($s['footer_privacy_url'], '#') }}">Privacy Policy</a>
            <span>·</span>
            <a href="{{ $url($s['footer_terms_url'], '#') }}">Terms of Use</a>
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
            <p>{{ $s['brand_name'] }} AI Assistant • Online</p>
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

// ===== ACTIVE NAV LINK =====
// Highlights the link of the section being viewed, and the one just clicked.
const navLinks = Array.from(primaryNavigation.querySelectorAll('a[href^="#"]'));
const navTargets = navLinks
    .map(a => ({ link: a, el: document.getElementById(a.getAttribute('href').slice(1)) }))
    .filter(t => t.el);
let navLockUntil = 0;

function setActiveNav(link) {
    navLinks.forEach(a => {
        const on = a === link;
        a.classList.toggle('active', on);
        if (on) a.setAttribute('aria-current', 'location'); else a.removeAttribute('aria-current');
    });
}

function updateActiveNav() {
    if (Date.now() < navLockUntil) return;
    const line = window.scrollY + 140;               // just under the fixed navbar
    let current = navTargets[0];
    navTargets.forEach(t => { if (t.el.getBoundingClientRect().top + window.scrollY <= line) current = t; });
    if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) {
        current = navTargets[navTargets.length - 1];  // bottom of the page
    }
    setActiveNav(current.link);
}

navLinks.forEach(a => a.addEventListener('click', () => {
    setActiveNav(a);
    navLockUntil = Date.now() + 1000;                 // let the smooth scroll finish
    setTimeout(updateActiveNav, 1050);
}));
window.addEventListener('scroll', updateActiveNav, { passive: true });
window.addEventListener('resize', updateActiveNav);
updateActiveNav();

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

const CLINIC = @json($vioraInfo);
const clinicHours = CLINIC.hours.map(h => '• ' + h.replace(/^([^:]+):/, '**$1:**')).join('\n');

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
        answer: `🕐 Our clinic hours are:\n\n${clinicHours}\n\nWe recommend booking an appointment before visiting! 📅`
    },
    {
        keys: ['register', 'sign up', 'create account'],
        answer: `Registering for ClinicRMS is easy and free! 🎉\n\n1️⃣ Click the "Register" button at the top\n2️⃣ Fill in your name, email, and password\n3️⃣ Verify your email address\n4️⃣ Done! You're ready to use the system ✅`
    },
    {
        keys: ['password', 'forgot', 'reset', "can't log in", 'login issue'],
        answer: `Having trouble logging in? Don't worry! 😊\n\n• **Forgot password:** Click "Forgot Password" on the login page and we'll send a reset link to your email.\n• **Can't log in:** Make sure your email has been verified. Check your inbox or spam folder.\n• **Still stuck?** Contact us at ${CLINIC.email} 📧`
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
        answer: `Yes, you can request a copy of your medical record! 📄\n\nRequests are processed following clinic policies and patient verification procedures.\n\nPlease contact us at:\n📧 ${CLINIC.email}\n📞 ${CLINIC.phone}`
    },
    {
        keys: ['wrong', 'incorrect', 'error in record', 'fix record'],
        answer: `If there's incorrect information in your record, please inform the clinic staff immediately so corrections can be made. 📝\n\nYou can also reach us at:\n📧 ${CLINIC.email}\n📞 ${CLINIC.phone}`
    },
    {
        keys: ['privacy', 'safe', 'data', 'security', 'confidential', 'information'],
        answer: `🔒 Your privacy is our priority!\n\n• **Encrypted connections (HTTPS)** protect your data\n• **Role-based access** — only your doctor and authorized staff can view your records\n• We never share your data with third parties\n\nYour information is in good hands! 💜`
    },
    {
        keys: ['doctor', 'specialist', 'available doctor'],
        answer: `👨‍⚕️ We have general practitioners and various specialists available.\n\nFor the most up-to-date list:\n• Log in and visit the **Doctors** section\n• Or contact us at:\n📞 ${CLINIC.phone}\n📧 ${CLINIC.email}`
    },
    {
        keys: ['location', 'where', 'address', 'map', 'directions', 'calauan', 'laguna', 'dayap'],
        answer: `📍 Our clinic is located at:\n\n**${CLINIC.address}**${CLINIC.directions ? '\n\n🚌 **How to get here:**\n' + CLINIC.directions : ''}${CLINIC.parking ? '\n\n🅿️ ' + CLINIC.parking : ''}`
    },
    {
        keys: ['mobile', 'phone', 'app', 'smartphone', 'tablet'],
        answer: `Yes! ClinicRMS is fully responsive and works on all devices — desktop, tablet, or smartphone. 📱\n\nNo app installation is required. Just open the site in your browser and you're good to go! ✅`
    },
    {
        keys: ['contact', 'reach', 'number', 'email', 'phone', 'support'],
        answer: `📞 Here is our contact information:\n\n• **Phone:** ${CLINIC.phones}\n• **Email:** ${CLINIC.email}\n• **Address:** ${CLINIC.address}\n\nYou can also fill out the contact form on this page! 😊`
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
    return `Sorry, I'm not able to answer that directly just yet. 😅\n\nYou can try:\n• One of the quick reply buttons below\n• Emailing us at **${CLINIC.email}**\n• Calling us at **${CLINIC.phone}**\n\nIs there anything else I can help you with? 💜`;
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