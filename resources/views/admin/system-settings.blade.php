@extends('layouts.admin')

@section('head')
{{-- Shared Admin look (hero, alerts, buttons) + this page's own layout --}}
<link rel="stylesheet" href="{{ asset('css/admin-website.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-system-settings.css') }}">
@endsection

@section('content')
@php
    $v       = fn (string $key) => old($key, $settings[$key] ?? '');
    $on      = fn (string $key) => old($key, $settings[$key] ?? '0') == '1';
    $lunchOn = $on('lunch_enabled');
    $sections = [
        'general'       => ['General',       'bx-building-house',  'Profile and logo'],
        'appointments'  => ['Appointments',  'bx-calendar-check',  'Hours and slots'],
        'notifications' => ['Notifications', 'bx-bell',            'Email and SMS'],
        'security'      => ['Security',      'bx-shield-quarter',  'Session timeout'],
    ];
    $sessionLabel = fn ($m) => $m >= 60 ? ($m / 60) . ' hour' . ($m > 60 ? 's' : '') : $m . ' minutes';
@endphp

<div class="ws-container ss-container">

    <div class="ws-hero">
        <div class="ws-hero-left">
            <div class="ws-hero-badge"><i class='bx bx-slider-alt'></i></div>
            <div>
                <h2>System Settings</h2>
                <p>Clinic information, appointment rules, notifications and session security for the whole system.</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="ws-alert ws-alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="ws-alert ws-alert-error" role="alert">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="ws-alert ws-alert-error" role="alert">
            <strong>Please fix the following:</strong>
            <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="ss-layout">

        {{-- ============ Section navigation ============ --}}
        <nav class="ss-nav" aria-label="Settings sections">
            @foreach($sections as $id => [$label, $icon, $hint])
                <a href="#{{ $id }}" data-section="{{ $id }}" class="{{ $loop->first ? 'active' : '' }}">
                    <i class='bx {{ $icon }}'></i>
                    <span class="ss-nav-text"><strong>{{ $label }}</strong><small>{{ $hint }}</small></span>
                    <span class="ss-nav-err" aria-label="Has errors"></span>
                </a>
            @endforeach
        </nav>

        <form method="POST" action="{{ route('admin.system-settings.update') }}" enctype="multipart/form-data"
              class="ss-main" id="systemSettingsForm" data-reset-url="{{ route('admin.system-settings.index') }}"
              data-has-errors="{{ $errors->any() ? '1' : '0' }}" novalidate>
            @csrf
            @method('PUT')

            {{-- ================= A. GENERAL ================= --}}
            <section class="ss-card" id="general" aria-labelledby="h-general">
                <header class="ss-card-head">
                    <span class="ss-card-icon"><i class='bx bx-building-house'></i></span>
                    <div>
                        <h3 id="h-general">General Settings</h3>
                        <p>Shown on SMS messages, appointment emails, the sidebar and the landing page.</p>
                    </div>
                </header>

                <div class="ss-general">
                    <div class="ss-general-fields">
                        <div class="ss-field">
                            <label for="clinic_name">Clinic name <span class="ss-req">*</span></label>
                            <input id="clinic_name" type="text" name="clinic_name" maxlength="100" required autocomplete="organization"
                                   value="{{ $v('clinic_name') }}" class="@error('clinic_name') is-invalid @enderror">
                            @error('clinic_name')<small class="ss-error">{{ $message }}</small>@enderror
                        </div>

                        <div class="ss-field">
                            <label for="clinic_address">Clinic address</label>
                            <input id="clinic_address" type="text" name="clinic_address" maxlength="255" autocomplete="street-address"
                                   placeholder="Street, Barangay, City, Province"
                                   value="{{ $v('clinic_address') }}" class="@error('clinic_address') is-invalid @enderror">
                            @error('clinic_address')<small class="ss-error">{{ $message }}</small>@enderror
                        </div>

                        <div class="ss-row-2">
                            <div class="ss-field">
                                <label for="clinic_phone">Contact number</label>
                                <input id="clinic_phone" type="text" name="clinic_phone" maxlength="30" inputmode="tel" autocomplete="tel"
                                       placeholder="09XX XXX XXXX"
                                       value="{{ $v('clinic_phone') }}" class="@error('clinic_phone') is-invalid @enderror">
                                @error('clinic_phone')<small class="ss-error">{{ $message }}</small>@enderror
                            </div>
                            <div class="ss-field">
                                <label for="clinic_email">Official email</label>
                                <input id="clinic_email" type="text" name="clinic_email" maxlength="150" inputmode="email" autocomplete="email"
                                       placeholder="clinic@example.com"
                                       value="{{ $v('clinic_email') }}" class="@error('clinic_email') is-invalid @enderror">
                                @error('clinic_email')<small class="ss-error">{{ $message }}</small>@enderror
                            </div>
                        </div>

                        <p class="ss-note"><i class='bx bx-info-circle'></i>
                            The landing page's contact details are managed separately in Website Management.</p>
                    </div>

                    {{-- Logo uploader --}}
                    <div class="ss-logo" id="logoZone" data-has-logo="{{ $logoUrl ? '1' : '0' }}">
                        <span class="ss-logo-label">Clinic logo</span>
                        <div class="ss-logo-tile" id="logoTile">
                            <img id="logoPreview" src="{{ $logoUrl }}" alt="Clinic logo preview" @unless($logoUrl) hidden @endunless>
                            <i class='bx bx-image-add' id="logoPlaceholder" @if($logoUrl) hidden @endif></i>
                        </div>
                        <p class="ss-logo-name" id="logoName">{{ $logoUrl ? 'Current logo' : 'No logo uploaded' }}</p>

                        <input id="clinic_logo" class="ss-sr" type="file" name="clinic_logo"
                               accept="image/png,image/jpeg,image/webp">
                        <label for="clinic_logo" class="ws-btn ws-btn-ghost ss-logo-btn">
                            <i class='bx bx-upload'></i> <span id="logoBtnText">{{ $logoUrl ? 'Replace logo' : 'Upload logo' }}</span>
                        </label>
                        <small class="ss-help">Drop an image here or click. JPG, PNG or WEBP, up to 2 MB.<br>Leave empty to keep the current logo.</small>
                        <small class="ss-error" id="logoClientError" role="alert" hidden></small>
                        @error('clinic_logo')<small class="ss-error">{{ $message }}</small>@enderror

                        @if($logoUrl)
                            <label class="ss-switch ss-switch-sm ss-remove" id="removeLogoWrap">
                                <input type="checkbox" name="remove_logo" value="1" id="removeLogo" @checked(old('remove_logo'))>
                                <span class="ss-track" aria-hidden="true"></span>
                                <span class="ss-switch-text">Remove current logo</span>
                            </label>
                        @endif
                    </div>
                </div>
            </section>

            {{-- ================= B. APPOINTMENTS ================= --}}
            <section class="ss-card" id="appointments" aria-labelledby="h-appointments">
                <header class="ss-card-head">
                    <span class="ss-card-icon"><i class='bx bx-calendar-check'></i></span>
                    <div>
                        <h3 id="h-appointments">Appointment Settings</h3>
                        <p>Checked when patients, staff and admins book or reschedule. Existing appointments are not changed.</p>
                    </div>
                </header>

                <div class="ss-block">
                    <h4 class="ss-block-title">Schedule</h4>

                    <div class="ss-field">
                        <span class="ss-label" id="durationLabel">Appointment duration</span>
                        <div class="ss-seg" role="radiogroup" aria-labelledby="durationLabel">
                            @foreach($durations as $d)
                                <label>
                                    <input type="radio" name="slot_minutes" value="{{ $d }}" @checked((int) $v('slot_minutes') === $d)>
                                    <span>{{ $d }} min</span>
                                </label>
                            @endforeach
                        </div>
                        @error('slot_minutes')<small class="ss-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="ss-row-2">
                        <div class="ss-field">
                            <label for="opening_time">Opening time</label>
                            <input id="opening_time" type="time" name="opening_time" required value="{{ $v('opening_time') }}"
                                   class="@error('opening_time') is-invalid @enderror">
                            @error('opening_time')<small class="ss-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="ss-field">
                            <label for="closing_time">Closing time</label>
                            <input id="closing_time" type="time" name="closing_time" required value="{{ $v('closing_time') }}"
                                   class="@error('closing_time') is-invalid @enderror">
                            <small class="ss-help">The last appointment must end by this time.</small>
                            @error('closing_time')<small class="ss-error">{{ $message }}</small>@enderror
                        </div>
                    </div>
                </div>

                <div class="ss-block">
                    <div class="ss-block-head">
                        <h4 class="ss-block-title">Lunch break</h4>
                        <input type="hidden" name="lunch_enabled" value="0">
                        <label class="ss-switch">
                            <input type="checkbox" name="lunch_enabled" value="1" id="lunch_enabled" @checked($lunchOn)>
                            <span class="ss-track" aria-hidden="true"></span>
                            <span class="ss-switch-text">No bookings during lunch</span>
                        </label>
                    </div>
                    <div class="ss-row-2" id="lunchFields">
                        <div class="ss-field">
                            <label for="lunch_start">Lunch starts</label>
                            <input id="lunch_start" type="time" name="lunch_start" value="{{ $v('lunch_start') }}"
                                   class="@error('lunch_start') is-invalid @enderror">
                            @error('lunch_start')<small class="ss-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="ss-field">
                            <label for="lunch_end">Lunch ends</label>
                            <input id="lunch_end" type="time" name="lunch_end" value="{{ $v('lunch_end') }}"
                                   class="@error('lunch_end') is-invalid @enderror">
                            @error('lunch_end')<small class="ss-error">{{ $message }}</small>@enderror
                        </div>
                    </div>
                </div>

                <div class="ss-block">
                    <h4 class="ss-block-title">Patient booking rules</h4>
                    <div class="ss-row-2">
                        <div class="ss-field">
                            <label for="booking_min_notice_hours">Minimum notice</label>
                            <div class="ss-suffix">
                                <input id="booking_min_notice_hours" type="number" name="booking_min_notice_hours" min="0" max="168" step="1" inputmode="numeric"
                                       value="{{ $v('booking_min_notice_hours') }}" class="@error('booking_min_notice_hours') is-invalid @enderror">
                                <span>hours</span>
                            </div>
                            <small class="ss-help" id="noticeHelp">0 = patients may book any future slot.</small>
                            @error('booking_min_notice_hours')<small class="ss-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="ss-field">
                            <label for="booking_max_advance_days">Maximum days ahead</label>
                            <div class="ss-suffix">
                                <input id="booking_max_advance_days" type="number" name="booking_max_advance_days" min="0" max="365" step="1" inputmode="numeric"
                                       value="{{ $v('booking_max_advance_days') }}" class="@error('booking_max_advance_days') is-invalid @enderror">
                                <span>days</span>
                            </div>
                            <small class="ss-help" id="windowHelp">0 = no limit. Applies to patient self-booking only.</small>
                            @error('booking_max_advance_days')<small class="ss-error">{{ $message }}</small>@enderror
                        </div>
                    </div>
                </div>

                <div class="ss-block ss-preview">
                    <div class="ss-block-head">
                        <h4 class="ss-block-title">Time slots preview</h4>
                        <span class="ss-count" id="slotCount">{{ collect($previewSlots)->where('lunch', false)->count() }} slots per day</span>
                    </div>
                    <p class="ss-preview-warn" id="slotWarn" role="alert" hidden></p>
                    <div class="ss-slots" id="slotList" aria-live="polite">
                        @foreach($previewSlots as $slot)
                            <span class="ss-slot {{ $slot['lunch'] ? 'is-lunch' : '' }}">{{ $slot['label'] }}</span>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- ================= C. NOTIFICATIONS ================= --}}
            <section class="ss-card" id="notifications" aria-labelledby="h-notifications">
                <header class="ss-card-head">
                    <span class="ss-card-icon"><i class='bx bx-bell'></i></span>
                    <div>
                        <h3 id="h-notifications">Notification Settings</h3>
                        <p>Choose which appointment messages are sent to patients.</p>
                    </div>
                </header>

                <input type="hidden" name="notify_email_enabled" value="0">
                <input type="hidden" name="notify_sms_enabled" value="0">
                <div class="ss-channels">
                    <label class="ss-channel">
                        <span class="ss-channel-icon"><i class='bx bx-envelope'></i></span>
                        <span class="ss-channel-text"><strong>Email</strong><small>Appointment status emails</small></span>
                        <span class="ss-switch ss-switch-sm">
                            <input type="checkbox" name="notify_email_enabled" value="1" id="master_email" @checked($on('notify_email_enabled')) aria-label="Email notifications">
                            <span class="ss-track" aria-hidden="true"></span>
                        </span>
                    </label>
                    <label class="ss-channel">
                        <span class="ss-channel-icon"><i class='bx bx-message-rounded-dots'></i></span>
                        <span class="ss-channel-text"><strong>SMS</strong><small>Text messages via iProgSMS</small></span>
                        <span class="ss-switch ss-switch-sm">
                            <input type="checkbox" name="notify_sms_enabled" value="1" id="master_sms" @checked($on('notify_sms_enabled')) aria-label="SMS notifications">
                            <span class="ss-track" aria-hidden="true"></span>
                        </span>
                    </label>
                </div>

                <div class="ss-table-wrap">
                    <table class="ss-table">
                        <thead><tr><th scope="col">Send a message when…</th><th scope="col">Email</th><th scope="col">SMS</th></tr></thead>
                        <tbody>
                        @foreach($events as $event => $meta)
                            <tr>
                                <th scope="row">{{ $meta['label'] }}</th>
                                @foreach(['email', 'sms'] as $channel)
                                    <td data-channel-cell="{{ $channel }}">
                                        @if(in_array($channel, $meta['channels'], true))
                                            @php $key = "notify_{$channel}_{$event}"; @endphp
                                            <input type="hidden" name="{{ $key }}" value="0">
                                            <span class="ss-switch ss-switch-sm">
                                                <input type="checkbox" name="{{ $key }}" value="1" data-channel="{{ $channel }}"
                                                       aria-label="{{ $meta['label'] }} by {{ $channel }}" @checked($on($key))>
                                                <span class="ss-track" aria-hidden="true"></span>
                                            </span>
                                        @else
                                            <span class="ss-na" aria-label="Not applicable">&mdash;</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="ss-note"><i class='bx bx-lock-alt'></i>
                    Account and security emails (verification codes, password reset, account approval) are always sent.
                    SMS and email credentials stay in the server configuration, not here.</p>
            </section>

            {{-- ================= D. SECURITY ================= --}}
            <section class="ss-card" id="security" aria-labelledby="h-security">
                <header class="ss-card-head">
                    <span class="ss-card-icon"><i class='bx bx-shield-quarter'></i></span>
                    <div>
                        <h3 id="h-security">Security Settings</h3>
                        <p>Applies to every role from their next request.</p>
                    </div>
                </header>

                <div class="ss-field ss-narrow">
                    <label for="session_lifetime">Session timeout (inactivity)</label>
                    <select id="session_lifetime" name="session_lifetime" class="@error('session_lifetime') is-invalid @enderror">
                        <option value="" @selected((string) $v('session_lifetime') === '')>Server default ({{ $serverLifetime }} minutes)</option>
                        @foreach($sessionOptions as $minutes)
                            <option value="{{ $minutes }}" @selected((string) $v('session_lifetime') === (string) $minutes)>{{ $sessionLabel($minutes) }}</option>
                        @endforeach
                    </select>
                    <small class="ss-help">Users are signed out after this much inactivity. Password rules and account approval are not affected.</small>
                    @error('session_lifetime')<small class="ss-error">{{ $message }}</small>@enderror
                </div>
            </section>

            {{-- ============ Sticky save bar ============ --}}
            <div class="ss-savebar" id="saveBar" role="region" aria-label="Save changes">
                <span class="ss-save-status" id="saveStatus" aria-live="polite">
                    <i class='bx bx-check-circle'></i> <span id="saveStatusText">All changes saved</span>
                </span>
                <span class="ss-save-actions">
                    <button type="button" class="ws-btn ws-btn-ghost" id="discardBtn" disabled>Discard</button>
                    <button type="submit" class="ws-btn ws-btn-primary" id="saveBtn" disabled><i class='bx bx-save'></i> Save Changes</button>
                </span>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/admin-system-settings.js') }}"></script>
@endsection