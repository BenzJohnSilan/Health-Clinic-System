@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-website.css') }}">
@endsection

@section('content')
@php
    $tabLabels = [
        'hero' => 'Hero', 'about' => 'About', 'services' => 'Services', 'features' => 'Portal Features',
        'steps' => 'How It Works', 'workflow' => 'Workflow', 'faqs' => 'FAQs',
        'contact' => 'Contact & Clinic Info', 'footer' => 'Footer',
    ];
    $tabIcons = [
        'hero' => 'bx-layout', 'about' => 'bx-info-circle', 'services' => 'bx-plus-medical', 'features' => 'bx-grid-alt',
        'steps' => 'bx-list-ol', 'workflow' => 'bx-git-merge', 'faqs' => 'bx-help-circle',
        'contact' => 'bx-phone-call', 'footer' => 'bx-dock-bottom',
    ];
@endphp

<div class="ws-container" data-active-tab="{{ $tab }}">

    {{-- Hero card: same look as the Admin Dashboard greeting card --}}
    <div class="ws-hero">
        <div class="ws-hero-left">
            <div class="ws-hero-badge"><i class='bx bx-globe'></i></div>
            <div>
                <h2>Website Management</h2>
                <p>Edit the text, pictures and lists on the public landing page. The layout and design stay the same.</p>
            </div>
        </div>
        <a href="{{ route('landing') }}" target="_blank" rel="noopener" class="ws-hero-btn">
            <i class='bx bx-link-external'></i> View live page
        </a>
    </div>

    @if(session('success'))
        <div class="ws-alert ws-alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="ws-alert ws-alert-error" role="alert">
            <strong>Please fix the following:</strong>
            <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="ws-tabs" role="tablist">
        @foreach($tabLabels as $key => $label)
            <button type="button" role="tab" class="ws-tab {{ $tab === $key ? 'active' : '' }}"
                    data-tab="{{ $key }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"><i class='bx {{ $tabIcons[$key] }}'></i><span>{{ $label }}</span></button>
        @endforeach
    </div>

    {{-- ============ HERO ============ --}}
    <div class="ws-panel {{ $tab === 'hero' ? 'active' : '' }}" data-panel="hero">
        <form method="POST" action="{{ route('admin.website.settings.update', 'hero') }}" enctype="multipart/form-data" class="ws-split" data-ws-form>
            @csrf @method('PUT')
            <div class="ws-card">
                <div class="ws-card-head"><div class="ws-card-title"><span class="ws-card-icon"><i class='bx bx-layout'></i></span><h3>Hero text</h3></div></div>
                @include('admin.website.partials.field', ['name' => 'hero_badge', 'label' => 'Badge (small text above heading)', 'max' => 80, 'help' => 'Leave empty to hide the badge.'])
                <div class="ws-grid-2">
                    @include('admin.website.partials.field', ['name' => 'hero_title_line1', 'label' => 'Heading – first line', 'max' => 80, 'required' => true])
                    @include('admin.website.partials.field', ['name' => 'hero_title_highlight', 'label' => 'Heading – highlighted line', 'max' => 80, 'help' => 'Shown in the accent colour on a second line.'])
                </div>
                @include('admin.website.partials.field', ['name' => 'hero_description', 'label' => 'Description', 'type' => 'textarea', 'rows' => 3, 'max' => 400, 'required' => true])
                <div class="ws-subhead">Buttons</div>
                <div class="ws-grid-2">
                    @include('admin.website.partials.field', ['name' => 'hero_primary_label', 'label' => 'Primary button label', 'max' => 40, 'required' => true])
                    @include('admin.website.partials.field', ['name' => 'hero_primary_url', 'label' => 'Primary button link', 'max' => 255, 'help' => 'Empty = Register page. Use #section, /path or https://…'])
                    @include('admin.website.partials.field', ['name' => 'hero_secondary_label', 'label' => 'Secondary button label', 'max' => 40, 'required' => true])
                    @include('admin.website.partials.field', ['name' => 'hero_secondary_url', 'label' => 'Secondary button link', 'max' => 255, 'help' => 'Empty = scroll to “About”.'])
                </div>
            </div>
            <aside class="ws-side">
                <div class="ws-card">
                    <div class="ws-card-head"><div class="ws-card-title"><span class="ws-card-icon"><i class='bx bx-image'></i></span><h3>Hero picture</h3></div></div>
                    @include('admin.website.partials.image-upload', ['name' => 'hero_image', 'label' => 'Picture (optional)', 'current' => $heroImageUrl])
                    <p class="ws-help">Shown on the right side of the hero, with no box around it. A transparent PNG looks best. Without a picture, the hero text is centred.</p>
                </div>
                <div class="ws-card ws-save-card">
                    <button type="submit" class="ws-btn ws-btn-primary"><i class='bx bx-save'></i> Save hero</button>
                    <a href="{{ route('admin.website.index', ['tab' => 'hero']) }}" class="ws-btn ws-btn-ghost">Cancel</a>
                </div>
            </aside>
        </form>
    </div>

    {{-- ============ ABOUT ============ --}}
    <div class="ws-panel {{ $tab === 'about' ? 'active' : '' }}" data-panel="about">
        <form method="POST" action="{{ route('admin.website.settings.update', 'about') }}" enctype="multipart/form-data" class="ws-split" data-ws-form>
            @csrf @method('PUT')
            <div class="ws-card">
                <div class="ws-card-head"><div class="ws-card-title"><span class="ws-card-icon"><i class='bx bx-info-circle'></i></span><h3>About text</h3></div></div>
                <div class="ws-grid-3">
                    @include('admin.website.partials.field', ['name' => 'about_label', 'label' => 'Small label', 'max' => 60, 'required' => true])
                    @include('admin.website.partials.field', ['name' => 'about_title', 'label' => 'Heading', 'max' => 80, 'required' => true])
                    @include('admin.website.partials.field', ['name' => 'about_title_highlight', 'label' => 'Highlighted word(s)', 'max' => 60])
                </div>
                @include('admin.website.partials.field', ['name' => 'about_text_1', 'label' => 'Introduction', 'type' => 'textarea', 'rows' => 4, 'max' => 1200, 'required' => true])
                @include('admin.website.partials.field', ['name' => 'about_text_2', 'label' => 'Supporting text', 'type' => 'textarea', 'rows' => 4, 'max' => 1200])
            </div>
            <aside class="ws-side">
                <div class="ws-card">
                    <div class="ws-card-head"><div class="ws-card-title"><span class="ws-card-icon"><i class='bx bx-image'></i></span><h3>About picture</h3></div></div>
                    @include('admin.website.partials.image-upload', ['name' => 'about_image', 'label' => 'Picture (optional)', 'current' => $aboutImageUrl])
                    <p class="ws-help">Replaces the clinic icon, with no box around it. A transparent PNG looks best.</p>
                </div>
                <div class="ws-card ws-save-card">
                    <button type="submit" class="ws-btn ws-btn-primary"><i class='bx bx-save'></i> Save about</button>
                    <a href="{{ route('admin.website.index', ['tab' => 'about']) }}" class="ws-btn ws-btn-ghost">Cancel</a>
                </div>
            </aside>
        </form>
    </div>

    {{-- ============ REPEATABLE SECTIONS ============ --}}
    <div class="ws-panel {{ $tab === 'services' ? 'active' : '' }}" data-panel="services">
        <p class="ws-note"><i class='bx bx-info-circle'></i> These are the public service cards only. Prices and billing services are managed separately under <a href="{{ route('admin.services.index') }}">Services / Fees</a> and are not affected here.</p>
        @include('admin.website.partials.section-heading', ['group' => 'services'])
        @include('admin.website.partials.items', ['section' => 'services', 'noun' => 'service', 'hasImage' => true])
    </div>

    <div class="ws-panel {{ $tab === 'features' ? 'active' : '' }}" data-panel="features">
        @include('admin.website.partials.section-heading', ['group' => 'features'])
        @include('admin.website.partials.items', ['section' => 'features', 'noun' => 'feature'])
    </div>

    <div class="ws-panel {{ $tab === 'steps' ? 'active' : '' }}" data-panel="steps">
        <p class="ws-note"><i class='bx bx-info-circle'></i> Step numbers (01, 02, …) follow the order below automatically.</p>
        @include('admin.website.partials.section-heading', ['group' => 'steps'])
        @include('admin.website.partials.items', ['section' => 'steps', 'noun' => 'step'])
    </div>

    <div class="ws-panel {{ $tab === 'workflow' ? 'active' : '' }}" data-panel="workflow">
        @include('admin.website.partials.section-heading', ['group' => 'workflow'])
        @include('admin.website.partials.items', ['section' => 'workflow', 'noun' => 'workflow step', 'hasBody' => false])
    </div>

    <div class="ws-panel {{ $tab === 'faqs' ? 'active' : '' }}" data-panel="faqs">
        <p class="ws-note"><i class='bx bx-info-circle'></i> Use <code>**text**</code> in an answer to make it bold. FAQs appear under their category tab on the page; a category with no visible FAQ is hidden.</p>
        @include('admin.website.partials.section-heading', ['group' => 'faqs'])
        @include('admin.website.partials.items', ['section' => 'faqs', 'noun' => 'FAQ', 'hasIcon' => false, 'isFaq' => true])
    </div>

    {{-- ============ CONTACT & CLINIC INFO ============ --}}
    <div class="ws-panel {{ $tab === 'contact' ? 'active' : '' }}" data-panel="contact">
        <div class="ws-stack">
        <form method="POST" action="{{ route('admin.website.settings.update', 'contact') }}" class="ws-card" data-ws-form>
            @csrf @method('PUT')
            <div class="ws-card-head"><div class="ws-card-title"><span class="ws-card-icon"><i class='bx bx-phone-call'></i></span><h3>Contact section</h3></div></div>
            <div class="ws-grid-3">
                @include('admin.website.partials.field', ['name' => 'contact_label', 'label' => 'Small label', 'max' => 60, 'required' => true])
                @include('admin.website.partials.field', ['name' => 'contact_title', 'label' => 'Heading', 'max' => 120, 'required' => true])
                @include('admin.website.partials.field', ['name' => 'contact_sub', 'label' => 'Subtitle', 'max' => 300])
            </div>
            <div class="ws-grid-3">
                @include('admin.website.partials.field', ['name' => 'contact_address', 'label' => 'Address', 'type' => 'textarea', 'rows' => 3, 'max' => 250, 'required' => true, 'help' => 'One line per row.'])
                @include('admin.website.partials.field', ['name' => 'contact_phones', 'label' => 'Phone number(s)', 'type' => 'textarea', 'rows' => 3, 'max' => 200, 'required' => true, 'help' => 'One number per line.'])
                @include('admin.website.partials.field', ['name' => 'contact_emails', 'label' => 'Email address(es)', 'type' => 'textarea', 'rows' => 3, 'max' => 250, 'required' => true, 'help' => 'One email per line.'])
            </div>
            <div class="ws-actions">
                <button type="submit" class="ws-btn ws-btn-primary"><i class='bx bx-save'></i> Save contact</button>
                <a href="{{ route('admin.website.index', ['tab' => 'contact']) }}" class="ws-btn ws-btn-ghost">Cancel</a>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.website.settings.update', 'location') }}" class="ws-card" data-ws-form>
            @csrf @method('PUT')
            <div class="ws-card-head"><div class="ws-card-title"><span class="ws-card-icon"><i class='bx bx-map'></i></span><h3>Clinic information, directions &amp; hours</h3></div></div>
            <div class="ws-grid-3">
                @include('admin.website.partials.field', ['name' => 'location_label', 'label' => 'Small label', 'max' => 60, 'required' => true])
                @include('admin.website.partials.field', ['name' => 'location_title', 'label' => 'Heading', 'max' => 120, 'required' => true])
                @include('admin.website.partials.field', ['name' => 'location_sub', 'label' => 'Subtitle', 'max' => 300])
            </div>
            <div class="ws-grid-2">
                @include('admin.website.partials.field', ['name' => 'location_address', 'label' => 'Clinic address', 'type' => 'textarea', 'rows' => 3, 'max' => 250, 'required' => true])
                @include('admin.website.partials.field', ['name' => 'location_hours', 'label' => 'Operating hours', 'type' => 'textarea', 'rows' => 3, 'max' => 250, 'required' => true, 'help' => 'One line per row, e.g. “Mon–Fri: 9:00 AM – 5:00 PM”.'])
                @include('admin.website.partials.field', ['name' => 'location_directions', 'label' => 'How to get there', 'type' => 'textarea', 'rows' => 3, 'max' => 600, 'help' => 'Leave empty to hide this card.'])
                @include('admin.website.partials.field', ['name' => 'location_parking', 'label' => 'Parking information', 'type' => 'textarea', 'rows' => 3, 'max' => 400, 'help' => 'Leave empty to hide this card.'])
            </div>
            @include('admin.website.partials.field', ['name' => 'location_map_url', 'label' => 'Google Maps embed address', 'type' => 'textarea', 'rows' => 2, 'max' => 1500, 'help' => 'Google Maps → Share → Embed a map → copy only the src="…" address. Must start with https://www.google.com/maps/embed'])
            <div class="ws-actions">
                <button type="submit" class="ws-btn ws-btn-primary"><i class='bx bx-save'></i> Save clinic information</button>
                <a href="{{ route('admin.website.index', ['tab' => 'contact']) }}" class="ws-btn ws-btn-ghost">Cancel</a>
            </div>
        </form>
        </div>
    </div>

    {{-- ============ FOOTER ============ --}}
    <div class="ws-panel {{ $tab === 'footer' ? 'active' : '' }}" data-panel="footer">
        <form method="POST" action="{{ route('admin.website.settings.update', 'footer') }}" class="ws-card" data-ws-form>
            @csrf @method('PUT')
            <div class="ws-card-head"><div class="ws-card-title"><span class="ws-card-icon"><i class='bx bx-dock-bottom'></i></span><h3>Brand &amp; footer</h3></div></div>
            <div class="ws-grid-2">
                @include('admin.website.partials.field', ['name' => 'brand_name', 'label' => 'Brand name (navigation bar, footer, chatbot)', 'max' => 40, 'required' => true, 'help' => 'Once a Clinic name is saved in System Settings, that name is shown on the landing page instead.'])
                @include('admin.website.partials.field', ['name' => 'footer_copyright_name', 'label' => 'Copyright name', 'max' => 80, 'required' => true])
            </div>
            @include('admin.website.partials.field', ['name' => 'footer_description', 'label' => 'Footer description', 'type' => 'textarea', 'rows' => 3, 'max' => 300, 'required' => true])
            <div class="ws-grid-3">
                @include('admin.website.partials.field', ['name' => 'footer_address', 'label' => 'Footer address', 'max' => 200, 'required' => true])
                @include('admin.website.partials.field', ['name' => 'footer_phone', 'label' => 'Footer phone', 'max' => 40, 'required' => true])
                @include('admin.website.partials.field', ['name' => 'footer_email', 'label' => 'Footer email', 'max' => 120, 'required' => true])
            </div>
            <div class="ws-grid-2">
                @include('admin.website.partials.field', ['name' => 'footer_privacy_url', 'label' => 'Privacy Policy link', 'max' => 255, 'help' => 'Use #section, /path or https://… (empty = no link yet).'])
                @include('admin.website.partials.field', ['name' => 'footer_terms_url', 'label' => 'Terms of Use link', 'max' => 255])
            </div>
            <div class="ws-actions">
                <button type="submit" class="ws-btn ws-btn-primary"><i class='bx bx-save'></i> Save footer</button>
                <a href="{{ route('admin.website.index', ['tab' => 'footer']) }}" class="ws-btn ws-btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>

{{-- ============ ADD / EDIT ITEM MODAL ============ --}}
<div class="ws-modal" id="wsItemModal" hidden>
    <div class="ws-modal-box" role="dialog" aria-modal="true" aria-labelledby="wsModalTitle">
        <button type="button" class="ws-modal-close js-ws-close" aria-label="Close">&times;</button>
        <h3 id="wsModalTitle">Add item</h3>
        <form method="POST" action="" enctype="multipart/form-data" id="wsItemForm" data-ws-form>
            @csrf
            <input type="hidden" name="_method" id="wsItemMethod" value="POST" disabled>
            <input type="hidden" name="section" id="wsItemSection">

            <div class="ws-field">
                <label for="wsItemTitle" id="wsItemTitleLabel">Title <span class="ws-req">*</span></label>
                <input type="text" id="wsItemTitle" name="title" maxlength="255" required>
            </div>

            <div class="ws-field" data-only="faq">
                <label for="wsItemCategory">Category <span class="ws-req">*</span></label>
                <select id="wsItemCategory" name="category">
                    @foreach($faqCategories as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
            </div>

            <div class="ws-field" data-only="body">
                <label for="wsItemBody" id="wsItemBodyLabel">Description <span class="ws-req">*</span></label>
                <textarea id="wsItemBody" name="body" rows="4" maxlength="1500"></textarea>
            </div>

            <div class="ws-field" data-only="icon">
                <label for="wsItemIcon">Icon <span class="ws-req">*</span></label>
                <div class="ws-icon-input">
                    <span class="ws-icon-preview"><i class='bx bx-plus-medical' id="wsIconPreview"></i></span>
                    <input type="text" id="wsItemIcon" name="icon" list="wsIconList" maxlength="60" placeholder="bxs-heart" autocomplete="off">
                </div>
                <datalist id="wsIconList">
                    @foreach(['bx-plus-medical','bxs-heart','bxs-calendar-plus','bxs-calendar-check','bxs-calendar-week','bxs-report','bxs-file-find','bxs-id-card','bxs-bell-ring','bxs-user-plus','bxs-user-check','bxs-institution','bx-check-double','bx-log-in-circle','bx-badge-check','bx-message-square-detail','bxs-clinic','bxs-first-aid','bxs-injection','bxs-capsule','bxs-flask','bxs-donate-heart','bxs-time','bxs-phone-call','bxs-shield-plus','bxs-lock-alt','bxs-star'] as $ic)<option value="{{ $ic }}">@endforeach
                </datalist>
                <small class="ws-help">A <a href="https://boxicons.com" target="_blank" rel="noopener">Boxicons</a> class name, e.g. <code>bxs-heart</code>. Pick from the suggestions or type your own.</small>
            </div>

            <div class="ws-field" data-only="image">
                <label for="wsItemImage">Image (optional – replaces the icon)</label>
                <div class="ws-image-preview" id="wsItemImagePreview" hidden><img alt="Image preview"></div>
                <input type="file" id="wsItemImage" name="image" accept="image/jpeg,image/png,image/webp" data-preview="#wsItemImagePreview">
                <small class="ws-help">JPG, PNG or WebP, up to 2 MB. Shown in a small square, so use a simple, centred picture.</small>
                <label class="ws-check" id="wsRemoveImageWrap" hidden><input type="checkbox" name="remove_image" value="1"> Remove current image</label>
            </div>

            <label class="ws-check"><input type="checkbox" name="is_active" id="wsItemActive" value="1" checked> Visible on the landing page</label>

            <div class="ws-actions">
                <button type="submit" class="ws-btn ws-btn-primary"><i class='bx bx-save'></i> Save</button>
                <button type="button" class="ws-btn ws-btn-ghost js-ws-close">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="{{ asset('js/admin-website.js') }}"></script>
@endsection