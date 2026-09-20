<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Staff Panel - Clinic Record System</title>

<!-- Boxicons -->
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- CSS -->
<link rel="stylesheet" href="{{ asset('css/staff-layout.css') }}">

@yield('head')
</head>
<body>

@php
    $staff = auth()->user();

    $initials = strtoupper(
        substr($staff->first_name ?? '', 0, 1) .
        substr($staff->last_name  ?? '', 0, 1)
    );

    // Notifications + unread count are provided by the layouts.staff view
    // composer (see AppServiceProvider::boot) via StaffNotificationService,
    // which cross-references the persistent staff_notification_reads table.
    // $staffNotifications / $staffUnreadCount are always available here,
    // but default them defensively just in case.
    $staffNotifications = $staffNotifications ?? collect();
    $staffUnreadCount   = $staffUnreadCount ?? 0;
@endphp

<!-- ================= SIDEBAR ================= -->
<div class="sidebar" id="sidebar">

    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class='bx bx-plus-medical'></i>
        </div>
        <div class="brand-text">
            <h2>Clinic Record</h2>
            <p>Staff Panel</p>
        </div>
    </div>

    <div class="sidebar-nav-wrapper">

        <span class="nav-section-label">Main</span>
        <a href="{{ route('staff.dashboard') }}"
           class="{{ request()->routeIs('staff.dashboard') ? 'active' : '' }}">
            <i class='bx bx-home-alt'></i>
            <span>Dashboard</span>
        </a>

        <span class="nav-section-label">Appointments</span>
        <a href="{{ route('staff.appointments.index') }}"
           class="{{ request()->routeIs('staff.appointments.*') || request()->routeIs('staff.pending-appointments') ? 'active' : '' }}">
            <i class='bx bx-calendar'></i>
            <span>Appointments</span>
        </a>

        <span class="nav-section-label">Records</span>
        @if($staff->hasPermission('view_patients'))
        <a href="{{ route('staff.patients.index') }}"
           class="{{ request()->routeIs('staff.patients.*') ? 'active' : '' }}">
            <i class='bx bx-user'></i>
            <span>Patients</span>
        </a>
        @endif
        @if($staff->hasPermission('view_billing'))
        <a href="{{ route('staff.billing.index') }}"
           class="{{ request()->routeIs('staff.billing.*') ? 'active' : '' }}">
            <i class='bx bx-receipt'></i>
            <span>Billing &amp; Payments</span>
        </a>
        @endif

        @if($staff->hasPermission('view_medicine_inventory'))
            <span class="nav-section-label">Inventory</span>
            <a href="{{ route('staff.medicines.index') }}"
               class="{{ request()->routeIs('staff.medicines.*') ? 'active' : '' }}">
                <i class='bx bx-capsule'></i>
                <span>Medicine</span>
            </a>
        @endif

        <span class="nav-section-label">Account</span>
        <a href="{{ route('staff.activity-logs') }}"
           class="{{ request()->routeIs('staff.activity-logs') ? 'active' : '' }}">
            <i class='bx bx-history'></i>
            <span>Activity Logs</span>
        </a>
        <a href="{{ route('staff.settings') }}"
        class="{{ request()->routeIs('staff.settings') ? 'active' : '' }}">
            <i class='bx bx-cog'></i>
            <span>Account Settings</span>
        </a>

    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user-card">
            @if($staff->avatar)
                <img class="user-avatar"
                     src="{{ asset('storage/'.$staff->avatar) }}"
                     alt="Avatar">
            @else
                <div class="user-avatar-placeholder">{{ $initials }}</div>
            @endif
            <div class="user-info">
                <div class="user-name">{{ $staff->first_name }} {{ $staff->last_name }}</div>
                <div class="user-role">{{ $staff->role }}</div>
            </div>
        </div>
    </div>

</div>
<!-- END SIDEBAR -->


<!-- ================= MAIN CONTENT ================= -->
<div class="main-content" id="mainContent">
<script>
// Apply the saved sidebar state immediately, before the rest of the page
// renders, so there is no visible flash of the expanded sidebar first.
(function () {
    var sidebarEl = document.getElementById('sidebar');
    var mainEl    = document.getElementById('mainContent');

    // Disable the transition for this first frame — otherwise the browser
    // can paint one frame at the default width before 'collapsed' lands,
    // and the transition animates that brief flash into view.
    sidebarEl.classList.add('no-transition');
    mainEl.classList.add('no-transition');

    try {
        if (localStorage.getItem('clinicrms_sidebar_collapsed') === 'true' && window.innerWidth > 900) {
            sidebarEl.classList.add('collapsed');
            mainEl.classList.add('collapsed');
        }
    } catch (e) {}

    // Re-enable transitions after the first paint so manual toggling still
    // animates normally.
    requestAnimationFrame(function () {
        requestAnimationFrame(function () {
            sidebarEl.classList.remove('no-transition');
            mainEl.classList.remove('no-transition');
        });
    });
})();
</script>

    <!-- ================= HEADER ================= -->
    <div class="admin-header">

        <div class="header-left">
            <i class='bx bx-menu hamburger' id="hamburgerBtn"></i>

            <div class="breadcrumb">
                <span class="bc-role">
                    <i class='bx bx-user'></i>
                    {{ $staff->role }}
                </span>
                <span class="bc-sep">›</span>
                <span class="bc-page" id="bcPageLabel">Dashboard</span>
            </div>
        </div>

        <div class="header-right">

            <div class="header-date">
                <i class='bx bx-time-five'></i>
                <span id="headerDateTime">Loading...</span>
            </div>

            <!-- ===== NOTIFICATION BELL ===== -->
            <div class="notif-wrapper">
                <button class="header-bell" id="notifBtn" title="Notifications" aria-label="Notifications">
                    <i class='bx bx-bell'></i>
                    @if($staffUnreadCount > 0)
                        <span class="notif-badge" id="notifBadge">{{ $staffUnreadCount }}</span>
                    @endif
                </button>

                <!-- Notification Panel -->
                <div class="notif-panel" id="notifPanel">

                    <!-- Panel Header -->
                    <div class="notif-panel-header">
                        <div class="notif-panel-title">
                            <i class='bx bx-bell'></i>
                            <span>Notifications</span>
                        </div>
                        <span class="notif-panel-count" id="notifPanelCount" style="{{ $staffUnreadCount > 0 ? '' : 'display:none;' }}">
                            {{ $staffUnreadCount }} new
                        </span>
                    </div>

                    <!-- Mark all as read -->
                    <div class="notif-panel-actions" id="notifMarkAllWrap" style="{{ $staffUnreadCount > 0 ? '' : 'display:none;' }}">
                        <button type="button" class="notif-mark-all-btn" id="notifMarkAllBtn">
                            <i class='bx bx-check-double'></i> Mark all as read
                        </button>
                    </div>

                    <!-- Filter Tabs -->
                    <div class="notif-tabs" id="notifTabs" role="tablist" aria-label="Notification filters">
                        <button type="button" class="notif-tab active" data-filter="all">All</button>
                        <button type="button" class="notif-tab" data-filter="unread">Unread</button>
                        <button type="button" class="notif-tab" data-filter="appointments">Appointments</button>
                    </div>

                    <!-- Notification List -->
                    <div class="notif-list" id="notifList">

                        @forelse($staffNotifications as $item)
                            <a href="{{ $item['url'] }}"
                               class="notif-item {{ $item['unread'] ? 'notif-item--unread' : '' }}"
                               data-key="{{ $item['key'] }}"
                               data-category="{{ $item['category'] }}"
                               data-unread="{{ $item['unread'] ? '1' : '0' }}">

                                <div class="notif-item-icon {{ $item['icon_class'] }}">
                                    <i class='bx {{ $item['icon'] }}'></i>
                                </div>

                                <div class="notif-item-body">
                                    <div class="notif-item-top">
                                        <span class="notif-item-label {{ $item['label_class'] }}">{{ $item['label'] }}</span>
                                    </div>
                                    <p class="notif-item-patient">{{ $item['title'] }}</p>
                                    <p class="notif-item-reason">{{ $item['message'] }}</p>
                                    <span class="notif-item-time">
                                        <i class='bx bx-time-five'></i>
                                        {{ $item['timestamp']->diffForHumans() }}
                                    </span>
                                </div>

                                @if($item['unread'])
                                    <div class="notif-item-dot"></div>
                                @endif

                            </a>
                        @empty
                            <div class="notif-empty">
                                <div class="notif-empty-icon">
                                    <i class='bx bx-bell-off'></i>
                                </div>
                                <p>No notifications</p>
                                <small>You're all caught up!</small>
                            </div>
                        @endforelse

                        <div class="notif-empty notif-hidden" id="notifNoMatch">
                            <div class="notif-empty-icon">
                                <i class='bx bx-filter-alt'></i>
                            </div>
                            <p>Nothing here</p>
                            <small>No notifications match this filter.</small>
                        </div>

                    </div>

                    @if($staffNotifications->isNotEmpty())
                        <div class="notif-panel-footer">
                            <a href="{{ route('staff.appointments.index', ['status' => 'Pending']) }}" class="notif-view-all">
                                View all pending appointments
                                <i class='bx bx-right-arrow-alt'></i>
                            </a>
                        </div>
                    @endif

                </div>
            </div>
            <!-- ===== END NOTIFICATION ===== -->

            <!-- Avatar + name + chevron → dropdown -->
            <div class="header-avatar-group" id="avatarGroup">
                @if($staff->avatar)
                    <img class="h-avatar"
                         src="{{ asset('storage/'.$staff->avatar) }}"
                         alt="Avatar">
                @else
                    <div class="h-avatar-initials">{{ $initials }}</div>
                @endif

                <span class="h-name">{{ $staff->first_name }}</span>
                <i class='bx bx-chevron-down h-chevron'></i>

                <!-- Dropdown (View Profile only — same as patient layout) -->
                <div class="h-dropdown" id="hDropdown">
                    <div class="h-dropdown-header">
                        @if($staff->avatar)
                            <img src="{{ asset('storage/'.$staff->avatar) }}" alt="Avatar">
                        @else
                            <div class="hd-initials">{{ $initials }}</div>
                        @endif
                        <div class="hd-info">
                            <span class="hd-name">{{ $staff->first_name }} {{ $staff->last_name }}</span>
                            <span class="hd-role">{{ $staff->role }}</span>
                        </div>
                    </div>
                    <a href="{{ route('staff.settings') }}">
                        <i class='bx bx-user-circle'></i> View Profile
                    </a>
                </div>
            </div>

            <!-- Logout icon button -->
            <button class="header-logout-btn" onclick="openLogoutModal()" title="Logout">
                <i class='bx bx-log-out'></i>
            </button>

        </div>
    </div>
    <!-- END HEADER -->

    <div class="page-content">
        @yield('content')
    </div>

</div>
<!-- END MAIN CONTENT -->


<!-- ================= LOGOUT MODAL ================= -->
<div class="logout-modal" id="logoutModal">
    <div class="logout-box">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h3>Ready to Leave?</h3>
        <p>Are you sure you want to logout from your account?</p>
        <div class="logout-buttons">
            <button class="btn-cancel" onclick="closeLogoutModal()">Cancel</button>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-confirm">Logout</button>
            </form>
        </div>
    </div>
</div>


<!-- ================= SCRIPTS ================= -->
<script src="{{ asset('js/sidebar-collapse.js') }}"></script>
<script>
/* ---- Avatar dropdown ---- */
const avatarGroup = document.getElementById('avatarGroup');
const hDropdown   = document.getElementById('hDropdown');

avatarGroup.addEventListener('click', (e) => {
    e.stopPropagation();
    hDropdown.classList.toggle('show');
    notifPanel.classList.remove('show');
});

/* ---- Notification panel ---- */
const notifBtn   = document.getElementById('notifBtn');
const notifPanel = document.getElementById('notifPanel');

notifBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    // Opening the bell/panel NEVER marks anything as read — only
    // clicking an individual notification or "Mark all as read" does.
    notifPanel.classList.toggle('show');
    hDropdown.classList.remove('show');
});

/* ---- Notification read/unread (persistent, DB-backed) ---- */
(function () {
    const csrfToken   = document.querySelector('meta[name="csrf-token"]')?.content;
    const notifList   = document.getElementById('notifList');
    const notifBadge  = document.getElementById('notifBadge');
    const notifCount  = document.getElementById('notifPanelCount');
    const markAllWrap = document.getElementById('notifMarkAllWrap');
    const markAllBtn  = document.getElementById('notifMarkAllBtn');
    const tabs        = document.getElementById('notifTabs');
    const noMatch     = document.getElementById('notifNoMatch');

    if (!notifList) return;

    function updateUnreadUI(unreadCount) {
        if (unreadCount > 0) {
            if (notifBadge) {
                notifBadge.textContent = unreadCount;
                notifBadge.style.display = '';
            } else {
                const bell = document.getElementById('notifBtn');
                const span = document.createElement('span');
                span.className = 'notif-badge';
                span.id = 'notifBadge';
                span.textContent = unreadCount;
                bell.appendChild(span);
            }
            if (notifCount) {
                notifCount.textContent = unreadCount + ' new';
                notifCount.style.display = '';
            }
            if (markAllWrap) markAllWrap.style.display = '';
        } else {
            if (notifBadge) notifBadge.remove();
            if (notifCount) notifCount.style.display = 'none';
            if (markAllWrap) markAllWrap.style.display = 'none';
        }
    }

    function markItemRead(item) {
        item.classList.remove('notif-item--unread');
        item.dataset.unread = '0';
        const dot = item.querySelector('.notif-item-dot');
        if (dot) dot.remove();
    }

    // Click an individual notification -> mark it read (DB), then let the
    // browser follow the link as normal. Uses keepalive so the request
    // still completes even though the page is about to navigate away.
    notifList.addEventListener('click', function (e) {
        const item = e.target.closest('.notif-item');
        if (!item || item.dataset.unread !== '1') return;

        const key = item.dataset.key;
        markItemRead(item);

        const currentUnread = notifList.querySelectorAll('.notif-item--unread').length;
        updateUnreadUI(currentUnread);

        fetch(@json(route('staff.notifications.read')), {
            method: 'POST',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ key: key }),
        }).catch(() => {});
    });

    // Mark all as read
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function (e) {
            e.stopPropagation();

            notifList.querySelectorAll('.notif-item--unread').forEach(markItemRead);
            updateUnreadUI(0);

            fetch(@json(route('staff.notifications.read-all')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).catch(() => {});
        });
    }

    // Filter tabs (All / Unread / Appointments)
    if (tabs) {
        tabs.addEventListener('click', function (e) {
            const tab = e.target.closest('.notif-tab');
            if (!tab) return;

            // Without this, the click bubbles up to the document-level
            // "close on outside click" listener and closes the panel
            // right after switching tabs.
            e.stopPropagation();

            tabs.querySelectorAll('.notif-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            const filter = tab.dataset.filter;
            const items = notifList.querySelectorAll('.notif-item');
            let visibleCount = 0;

            items.forEach(item => {
                let show = true;
                if (filter === 'unread') {
                    show = item.dataset.unread === '1';
                } else if (filter !== 'all') {
                    show = item.dataset.category === filter;
                }
                item.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });

            if (noMatch) noMatch.classList.toggle('notif-hidden', items.length === 0 || visibleCount > 0);
        });
    }
})();

/* ---- Close on outside click ---- */
document.addEventListener('click', () => {
    hDropdown.classList.remove('show');
    notifPanel.classList.remove('show');
});

/* ---- Live date & time ---- */
function updateDateTime() {
    const now = new Date();
    const options = {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    };
    document.getElementById('headerDateTime').textContent =
        now.toLocaleString('en-US', options);
}
updateDateTime();
setInterval(updateDateTime, 1000);

/* ---- Active breadcrumb page label ---- */
(function () {
    const map = {
        'dashboard':            'Dashboard',
        'appointments':         'Appointments',
        'patients':             'Patients',
        'billing':              'Billing & Payments',
        'medicines':            'Medicine',
        'records':              'Records',
        'activity-logs':        'Activity Logs',
        'settings':             'Account Settings',
    };
    const path = window.location.pathname.toLowerCase();
    let label = 'Dashboard';
    for (const [key, val] of Object.entries(map)) {
        if (path.includes(key)) { label = val; break; }
    }
    document.getElementById('bcPageLabel').textContent = label;
})();

/* ---- Logout modal ---- */
function openLogoutModal()  { document.getElementById('logoutModal').classList.add('active'); }
function closeLogoutModal() { document.getElementById('logoutModal').classList.remove('active'); }
</script>

@yield('scripts')
</body>
</html>