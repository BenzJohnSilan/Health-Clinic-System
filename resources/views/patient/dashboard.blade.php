@extends('layouts.patient')

@section('head')
<link rel="stylesheet" href="{{ asset('css/patient-dashboard.css') }}">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- If JavaScript is disabled, skip the skeletons and show the real content -->
<noscript>
    <style>
        .swap-skeleton { display: none !important; }
        .swap-real { opacity: 1 !important; pointer-events: auto !important; }
    </style>
</noscript>
@endsection

@section('content')

<!-- ================================================================
     HERO SECTION (Greeting + CTA)
     Skeleton and real content are stacked in the same grid cell so the
     swap never shifts the layout — only opacity crossfades.
     ================================================================ -->
<div class="swap-wrapper hero-wrapper">

    <!-- SKELETON -->
    <div class="swap-item swap-skeleton skeleton-hero" aria-hidden="true">
        <div class="dashboard-hero">
            <div class="hero-greeting-group">
                <div class="skeleton-line skeleton-hero-icon"></div>
                <div class="hero-greeting">
                    <div class="skeleton-line skeleton-greeting-title"></div>
                    <div class="skeleton-line skeleton-greeting-subtitle"></div>
                </div>
            </div>
            <div class="skeleton-line skeleton-hero-btn"></div>
        </div>
    </div>

    <!-- REAL CONTENT -->
    <div class="swap-item swap-real">
        <div class="dashboard-hero">
            @php
                $greetingIcon = match($greeting) {
                    'Good Morning'   => 'bx-sun',
                    'Good Afternoon' => 'bxs-sun',
                    default          => 'bx-moon',
                };
            @endphp
            <div class="hero-greeting-group">
                <div class="hero-icon-badge"><i class='bx {{ $greetingIcon }}'></i></div>
                <div class="hero-greeting">
                    <h2 class="greeting-text">{{ $greeting }}, {{ $patient->first_name }}! 👋</h2>
                    <p class="greeting-subtitle">Here's an overview of your clinic visits and records.</p>
                </div>
            </div>

            @if($profileComplete)
                <a href="{{ route('patient.appointments.index') }}?book=1" class="hero-cta-btn">
                    <i class='bx bx-calendar-plus'></i> Book Appointment
                </a>
            @else
                <a href="{{ route('patient.settings') }}" class="hero-cta-btn">
                    <i class='bx bx-user-check'></i> Complete Profile
                </a>
            @endif
        </div>
    </div>
</div>

<!-- ================================================================
     MAIN CONTENT (2x2 overview grid + Recent Clinic Activity)
     Same skeleton/real stacking as the hero above.
     ================================================================ -->
<div class="swap-wrapper content-wrapper">

    <!-- ============ SKELETON ============ -->
    <div class="swap-item swap-skeleton" aria-hidden="true">

        <div class="overview-grid">

            {{-- Upcoming Appointment --}}
            <div class="info-card">
                <div class="info-card-header">
                    <div class="info-card-title">
                        <div class="skeleton-line sk-icon"></div>
                        <div class="skeleton-line sk-title"></div>
                    </div>
                    <div class="skeleton-line sk-pill"></div>
                </div>
                <div class="info-card-body">
                    <div class="skeleton-line sk-line w-70"></div>
                    <div class="skeleton-line sk-line w-55"></div>
                    <div class="skeleton-line sk-line w-40"></div>
                </div>
                <div class="info-card-footer">
                    <div class="skeleton-line sk-btn"></div>
                </div>
            </div>

            {{-- Profile Status --}}
            <div class="info-card">
                <div class="info-card-header">
                    <div class="info-card-title">
                        <div class="skeleton-line sk-icon"></div>
                        <div class="skeleton-line sk-title"></div>
                    </div>
                    <div class="skeleton-line sk-pill"></div>
                </div>
                <div class="info-card-body profile-status-body">
                    <div class="skeleton-line sk-ring"></div>
                    <div class="sk-lines-col">
                        <div class="skeleton-line sk-line w-100"></div>
                        <div class="skeleton-line sk-line w-70"></div>
                    </div>
                </div>
                <div class="info-card-footer">
                    <div class="skeleton-line sk-btn"></div>
                </div>
            </div>

            {{-- Latest Laboratory Result --}}
            <div class="info-card">
                <div class="info-card-header">
                    <div class="info-card-title">
                        <div class="skeleton-line sk-icon"></div>
                        <div class="skeleton-line sk-title"></div>
                    </div>
                    <div class="skeleton-line sk-pill"></div>
                </div>
                <div class="info-card-body">
                    <div class="skeleton-line sk-line sk-line--lg w-40"></div>
                    <div class="skeleton-line sk-line w-55"></div>
                    <div class="skeleton-line sk-line w-40"></div>
                </div>
                <div class="info-card-footer">
                    <div class="skeleton-line sk-btn"></div>
                </div>
            </div>

            {{-- Latest Medical Certificate --}}
            <div class="info-card">
                <div class="info-card-header">
                    <div class="info-card-title">
                        <div class="skeleton-line sk-icon"></div>
                        <div class="skeleton-line sk-title"></div>
                    </div>
                    <div class="skeleton-line sk-pill"></div>
                </div>
                <div class="info-card-body">
                    <div class="skeleton-line sk-line sk-line--lg w-55"></div>
                    <div class="skeleton-line sk-line w-85"></div>
                </div>
                <div class="info-card-footer">
                    <div class="skeleton-line sk-btn"></div>
                </div>
            </div>

        </div>

        {{-- Recent Clinic Activity --}}
        <div class="info-card activity-card">
            <div class="info-card-header">
                <div class="info-card-title">
                    <div class="skeleton-line sk-icon"></div>
                    <div class="skeleton-line sk-title"></div>
                </div>
            </div>
            <div class="activity-list">
                @for($i = 0; $i < 3; $i++)
                    <div class="activity-row">
                        <div class="skeleton-line sk-circle"></div>
                        <div class="skeleton-line sk-line w-55"></div>
                        <div class="skeleton-line sk-date"></div>
                        <div class="skeleton-line sk-pill"></div>
                    </div>
                @endfor
            </div>
        </div>

    </div>

    <!-- ============ REAL CONTENT ============ -->
    <div class="swap-item swap-real">

        <!-- ===== 2x2 OVERVIEW GRID: Upcoming Appointment / Profile Status /
             Latest Laboratory Result / Latest Medical Certificate ===== -->
        <div class="overview-grid">

            <!-- UPCOMING APPOINTMENT -->
            <div class="info-card">
                <div class="info-card-header">
                    <div class="info-card-title">
                        <span class="info-card-icon info-card-icon--purple"><i class='bx bx-calendar-check'></i></span>
                        <h3>Upcoming Appointment</h3>
                    </div>
                    @if($nextAppointment)
                        <span class="status-pill status-pill--green"><span class="status-dot"></span>{{ $nextAppointment->status }}</span>
                    @endif
                </div>

                @if($nextAppointment)
                    @php
                        $doctorFullName = trim(($nextAppointment->doctor->first_name ?? '') . ' ' . ($nextAppointment->doctor->last_name ?? ''));
                    @endphp
                    <div class="info-card-body">
                        <div class="info-row">
                            <span class="info-row-item"><i class='bx bx-calendar'></i> {{ optional($nextAppointment->appointment_date)->format('F d, Y') }}</span>
                            @if($nextAppointment->appointment_time)
                                <span class="info-row-item"><i class='bx bx-time-five'></i> {{ $nextAppointment->timeLabel('g:i A') }}</span>
                            @endif
                        </div>
                        <div class="info-row">
                            <span class="info-row-item"><i class='bx bx-user'></i> {{ $doctorFullName !== '' ? 'Dr. ' . $doctorFullName : 'Doctor to be assigned' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-row-item"><i class='bx bx-plus-medical'></i> {{ $nextAppointment->reason ?: 'Consultation' }}</span>
                        </div>
                    </div>

                    <div class="info-card-footer">
                        <a href="{{ route('patient.appointments.show', $nextAppointment->id) }}" class="card-view-btn">
                            View Details <i class='bx bx-chevron-right'></i>
                        </a>
                    </div>
                @else
                    <div class="info-card-empty">
                        <i class='bx bx-calendar-x'></i>
                        <p>No upcoming appointments.</p>
                    </div>
                @endif
            </div>

            <!-- PROFILE STATUS -->
            <div class="info-card">
                <div class="info-card-header">
                    <div class="info-card-title">
                        <span class="info-card-icon {{ $profileComplete ? 'info-card-icon--green' : 'info-card-icon--blue' }}"><i class='bx bxs-user-circle'></i></span>
                        <h3>Profile Status</h3>
                    </div>
                    @if($profileComplete)
                        <span class="status-pill status-pill--green"><i class='bx bx-check-circle'></i> Complete</span>
                    @else
                        <span class="status-pill status-pill--amber">{{ $profileCompletionPercent }}%</span>
                    @endif
                </div>

                <div class="info-card-body profile-status-body">
                    @php
                        $ringRadius = 42;
                        $ringCircumference = round(2 * pi() * $ringRadius, 2);
                        $ringOffset = round($ringCircumference * (1 - ($profileCompletionPercent / 100)), 2);
                    @endphp
                    <div class="profile-ring-wrap {{ $profileComplete ? 'profile-ring-wrap--complete' : '' }}">
                        <svg viewBox="0 0 100 100" class="profile-ring">
                            <circle cx="50" cy="50" r="{{ $ringRadius }}" class="profile-ring-track"></circle>
                            <circle cx="50" cy="50" r="{{ $ringRadius }}" class="profile-ring-progress"
                                    style="stroke-dasharray: {{ $ringCircumference }}; stroke-dashoffset: {{ $ringOffset }};"></circle>
                        </svg>
                        <div class="profile-ring-label">{{ $completedRequiredFields }}/{{ $totalRequiredFields }}</div>
                        @if($profileComplete)
                            <span class="profile-ring-badge"><i class='bx bx-check'></i></span>
                        @endif
                    </div>
                    <p class="profile-status-text">
                        {{ $profileComplete
                            ? 'All required information is completed.'
                            : $completedRequiredFields . ' of ' . $totalRequiredFields . ' required fields completed.' }}
                    </p>
                </div>

                <div class="info-card-footer">
                    <a href="{{ route('patient.settings') }}" class="card-view-btn">
                        View Profile <i class='bx bx-chevron-right'></i>
                    </a>
                </div>
            </div>

            <!-- LATEST LABORATORY RESULT -->
            <div class="info-card">
                <div class="info-card-header">
                    <div class="info-card-title">
                        <span class="info-card-icon info-card-icon--purple"><i class='bx bxs-flask'></i></span>
                        <h3>Latest Laboratory Result</h3>
                    </div>
                    @if($latestLabRequest)
                        @php
                            $labPillClass = match($latestLabStatusLabel) {
                                'Reviewed'            => 'status-pill--green',
                                'Correction Needed'   => 'status-pill--red',
                                'Result Received'     => 'status-pill--blue',
                                default               => 'status-pill--amber',
                            };
                        @endphp
                        <span class="status-pill {{ $labPillClass }}"><i class='bx bx-check-circle'></i> {{ $latestLabStatusLabel }}</span>
                    @endif
                </div>

                @if($latestLabRequest)
                    <div class="info-card-body">
                        <p class="info-card-main-text">{{ $latestLabRequest->testsDisplay() }}</p>
                        @if($latestLabDate)
                            <div class="info-row">
                                <span class="info-row-item"><i class='bx bx-calendar'></i> {{ \Carbon\Carbon::parse($latestLabDate)->format('F d, Y') }}</span>
                            </div>
                        @endif
                        <div class="info-row">
                            <span class="info-row-item"><i class='bx bx-file'></i> Laboratory Test</span>
                        </div>
                    </div>

                    <div class="info-card-footer">
                        <a href="{{ route('patient.laboratory-results.index') }}" class="card-view-btn">
                            View Result <i class='bx bx-chevron-right'></i>
                        </a>
                    </div>
                @else
                    <div class="info-card-empty">
                        <i class='bx bxs-flask'></i>
                        <p>No laboratory results yet.</p>
                    </div>
                @endif
            </div>

            <!-- LATEST MEDICAL CERTIFICATE -->
            <div class="info-card">
                <div class="info-card-header">
                    <div class="info-card-title">
                        <span class="info-card-icon info-card-icon--purple"><i class='bx bx-file-blank'></i></span>
                        <h3>Latest Medical Certificate</h3>
                    </div>
                    @if($latestCertificate)
                        <span class="status-pill status-pill--green"><i class='bx bx-check-circle'></i> {{ ucfirst($latestCertificate->status) }}</span>
                    @endif
                </div>

                @if($latestCertificate)
                    <div class="info-card-body">
                        <p class="info-card-main-text">{{ $latestCertificate->displayPurpose() }}</p>
                        @if($latestCertificate->released_at)
                            <div class="info-row">
                                <span class="info-row-item"><i class='bx bx-calendar'></i> {{ $latestCertificate->released_at->format('F d, Y') }}</span>
                            </div>
                        @endif
                        <div class="info-row">
                            <span class="info-row-item"><i class='bx bx-file'></i> {{ $latestCertificate->certificate_number }}</span>
                        </div>
                    </div>

                    <div class="info-card-footer">
                        <a href="{{ route('patient.medical-certificates.show', $latestCertificate->id) }}" class="card-view-btn">
                            View Certificate <i class='bx bx-chevron-right'></i>
                        </a>
                    </div>
                @else
                    <div class="info-card-empty">
                        <i class='bx bx-file-blank'></i>
                        <p>No medical certificates yet.</p>
                    </div>
                @endif
            </div>

        </div>

        <!-- ===== RECENT CLINIC ACTIVITY (latest 3 only) ===== -->
        <div class="info-card activity-card">
            <div class="info-card-header">
                <div class="info-card-title">
                    <span class="info-card-icon info-card-icon--purple"><i class='bx bx-history'></i></span>
                    <h3>Recent Clinic Activity</h3>
                </div>
            </div>

            <div class="activity-list">
                @forelse($recentNotifications as $item)
                    <a href="{{ $item['url'] }}" class="activity-row">
                        <span class="activity-icon {{ $item['icon_class'] }}"><i class='bx {{ $item['icon'] }}'></i></span>
                        <span class="activity-title">{{ $item['title'] }}</span>
                        <span class="activity-date">{{ $item['timestamp']->format('M d, Y') }}</span>
                        <span class="status-pill {{ $item['label_class'] ?: 'status-pill--gray' }}">{{ $item['label'] }}</span>
                    </a>
                @empty
                    <div class="info-card-empty">
                        <i class='bx bx-bell-off'></i>
                        <p>No recent activity yet.</p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</div>

<!-- ================================================================
     SCRIPT — crossfade every skeleton into its real content
     ================================================================ -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    const SKELETON_TIME = 400; // ms the skeletons stay visible

    setTimeout(function () {
        document.querySelectorAll('.swap-wrapper').forEach(function (wrapper) {
            const skeleton = wrapper.querySelector('.swap-skeleton');
            const real     = wrapper.querySelector('.swap-real');
            if (!skeleton || !real) return;

            real.classList.add('fade-in');
            skeleton.classList.add('fade-out');

            setTimeout(function () {
                skeleton.classList.add('removed');
            }, 320);
        });
    }, SKELETON_TIME);
});
</script>

@endsection