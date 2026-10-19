<!DOCTYPE html>
@php
    // Same public content (brand name, footer text, contact details) the landing page uses.
    // LandingPageContent falls back to the defaults, so this can never break the page.
    $s = \App\Services\LandingPageContent::settings();
    $logo = \App\Services\SystemSettings::logoUrl();
    $home = rtrim(route('landing'), '/') . '/';
@endphp
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title') | {{ $s['brand_name'] }}</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
{{-- landing-page.css gives these pages the exact navbar + footer of the landing page --}}
<link rel="stylesheet" href="{{ asset('css/landing-page.css') }}">
<link rel="stylesheet" href="{{ asset('css/legal.css') }}">
</head>

<body class="legal-page">

<a class="skip-link" href="#main-content">Skip to content</a>

<!-- ===== NAVBAR (same as the landing page; section links go back to the landing page) ===== -->
<nav aria-label="Main navigation">
    <a class="nav-brand" href="{{ $home }}#home">
        @if($logo)
            <span class="nav-brand-mark has-logo"><img src="{{ $logo }}" alt=""></span>
        @else
            <span class="nav-brand-mark"><i class='bx bx-plus-medical' aria-hidden="true"></i></span>
        @endif
        <span class="nav-brand-name">{{ $s['brand_name'] }}</span>
    </a>

    <button class="nav-toggle" id="navToggle" type="button" aria-expanded="false" aria-controls="primaryNavigation" aria-label="Open navigation menu">
        <i class='bx bx-menu'></i>
    </button>

    <div class="nav-menu" id="primaryNavigation">
        <ul class="nav-center">
            <li><a href="{{ $home }}#home">Home</a></li>
            <li><a href="{{ $home }}#about">About Us</a></li>
            <li><a href="{{ $home }}#services">Services</a></li>
            <li><a href="{{ $home }}#how-it-works">How It Works</a></li>
            <li><a href="{{ $home }}#contact">Contact</a></li>
        </ul>

        <div class="nav-links">
            <a href="{{ route('login') }}" class="btn-nav-outline">Login</a>
            <a href="{{ route('register') }}" class="btn-nav-fill">Register <i class='bx bx-right-arrow-alt' aria-hidden="true"></i></a>
        </div>
    </div>
</nav>

<main id="main-content">
    <div class="legal-wrap">
        <h1 class="legal-title">@yield('heading')</h1>

        <article class="legal-content">
            @yield('content')
        </article>

        {{-- A div, not <nav>: landing-page.css styles every <nav> as the fixed top bar --}}
        <div class="legal-switch" role="navigation" aria-label="Legal pages">
            <a href="{{ route('terms') }}" @class(['is-current' => request()->routeIs('terms')])>Terms and Conditions</a>
            <span aria-hidden="true">·</span>
            <a href="{{ route('privacy') }}" @class(['is-current' => request()->routeIs('privacy')])>Privacy Policy</a>
        </div>
    </div>
</main>

<!-- ===== FOOTER (same as the landing page) ===== -->
<footer>
    <div class="footer-inner">
        <div class="footer-col footer-brand">
            <a class="footer-logo" href="{{ $home }}#home">
                @if($logo)
                    <img src="{{ $logo }}" alt="" style="height:26px; width:auto; max-width:60px; object-fit:contain; vertical-align:middle; border-radius:4px;">
                @else
                    <i class='bx bx-plus-medical'></i>
                @endif
                {{ $s['brand_name'] }}
            </a>
            <p>{{ $s['footer_description'] }}</p>
        </div>

        <div class="footer-col">
            <h5>Quick Links</h5>
            <ul>
                <li><a href="{{ $home }}#home">Home</a></li>
                <li><a href="{{ $home }}#about">About Us</a></li>
                <li><a href="{{ $home }}#services">Services</a></li>
                <li><a href="{{ $home }}#how-it-works">How It Works</a></li>
                <li><a href="{{ $home }}#faqs">FAQs</a></li>
                <li><a href="{{ $home }}#contact">Contact</a></li>
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
            <a href="{{ route('privacy') }}">Privacy Policy</a>
            <span>·</span>
            <a href="{{ route('terms') }}">Terms and Conditions</a>
        </div>
    </div>
</footer>

<script>
// ===== MOBILE MENU (same behaviour as the landing page) =====
const navToggle = document.getElementById('navToggle');
const navToggleIcon = navToggle.querySelector('i');
const primaryNavigation = document.getElementById('primaryNavigation');
const siteNav = document.querySelector('nav');

function setNavOpen(isOpen) {
    primaryNavigation.classList.toggle('is-open', isOpen);
    navToggle.setAttribute('aria-expanded', isOpen);
    navToggle.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
    navToggleIcon.classList.toggle('bx-x', isOpen);
    navToggleIcon.classList.toggle('bx-menu', !isOpen);
}

navToggle.addEventListener('click', () => setNavOpen(!primaryNavigation.classList.contains('is-open')));
primaryNavigation.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setNavOpen(false)));

document.addEventListener('click', event => {
    if (primaryNavigation.classList.contains('is-open') && !event.target.closest('nav')) setNavOpen(false);
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && primaryNavigation.classList.contains('is-open')) {
        setNavOpen(false);
        navToggle.focus();
    }
});

// ===== NAVBAR SHADOW ON SCROLL =====
const syncNavShadow = () => siteNav.classList.toggle('is-scrolled', window.scrollY > 8);
window.addEventListener('scroll', syncNavShadow, { passive: true });
syncNavShadow();
</script>

</body>
</html>