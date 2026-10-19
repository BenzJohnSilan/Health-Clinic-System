@extends('layouts.admin')

@section('head')
{{-- Same stylesheet as Manage Users / Appointments so the search bar, table and pagination match. --}}
<link rel="stylesheet" href="{{ asset('css/admin-users.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-contact-messages.css') }}">
@endsection

@section('content')

<div class="container">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h1>Contact Messages</h1>
    </div>

    <!-- ================= SEARCH & FILTER ================= -->
    <form method="GET" action="{{ route('admin.contact-messages.index') }}" class="filter-bar">
        <input
            type="text"
            name="search"
            class="filter-input"
            placeholder="Search name, email, contact number, or message..."
            value="{{ $search }}">

        <select name="status" class="filter-input">
            <option value="">All Status</option>
            <option value="unread" {{ $status === 'unread' ? 'selected' : '' }}>Unread</option>
            <option value="read"   {{ $status === 'read'   ? 'selected' : '' }}>Read</option>
        </select>

        <select name="notice" class="filter-input">
            <option value="">All Email Notices</option>
            <option value="sent"   {{ $notice === 'sent'   ? 'selected' : '' }}>Sent</option>
            <option value="failed" {{ $notice === 'failed' ? 'selected' : '' }}>Not sent</option>
        </select>

        <button type="submit" class="btn-filter">
            <i class="fa-solid fa-filter"></i> Filter
        </button>

        @if($search !== '' || $status !== 'all' || $notice !== '')
            <a href="{{ route('admin.contact-messages.index') }}" class="btn-clear-filter">
                Clear
            </a>
        @endif
    </form>

    <!-- ================= MESSAGES ================= -->
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <!-- ================= TABLE ================= -->
    <div class="table-container">
    <table class="users-table">
        <thead>
            <tr>
                <th>Sender</th>
                <th>Email</th>
                <th>Contact Number</th>
                <th>Status</th>
                <th>Email Notice</th>
                <th>Submitted</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse($messages as $m)
            <tr class="{{ $m->isUnread() ? 'cm-row-unread' : '' }}">
                <td>
                    <span class="cm-sender">{{ $m->full_name }}</span>
                </td>
                <td>{{ $m->email }}</td>
                <td>{{ $m->contact_number }}</td>
                <td>
                    @if($m->isUnread())
                        <span class="badge badge--unread">Unread</span>
                    @else
                        <span class="badge badge--read">Read</span>
                    @endif
                    @if($m->replies_count > 0)
                        <span class="badge badge--replied">Replied</span>
                    @endif
                </td>
                <td>
                    @if($m->notified_at)
                        <span class="badge badge--sent" title="Emailed {{ $m->notified_at->format('M d, Y g:i A') }}">Sent</span>
                    @else
                        <span class="badge badge--notsent" title="{{ $m->notification_error }}">Not sent</span>
                    @endif
                </td>
                <td class="cm-date">{{ $m->created_at->format('M d, Y g:i A') }}</td>
                <td>
                    <div class="cm-actions">
                        <a class="cm-view-link" href="{{ route('admin.contact-messages.show', $m) }}">View</a>

                        <div class="action-menu">
                            <button type="button" class="action-menu-toggle" aria-haspopup="true" aria-expanded="false" aria-label="More actions">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>

                            <div class="action-menu-dropdown">
                                @if($m->isUnread())
                                    <form method="POST" action="{{ route('admin.contact-messages.read', $m) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="action-menu-item">
                                            <i class="fa-solid fa-envelope-open"></i> Mark as Read
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.contact-messages.unread', $m) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="action-menu-item">
                                            <i class="fa-solid fa-envelope"></i> Mark as Unread
                                        </button>
                                    </form>
                                @endif

                                <div class="action-menu-divider"></div>

                                <form method="POST" action="{{ route('admin.contact-messages.destroy', $m) }}"
                                      onsubmit="return confirm('Move this message to the Archive? Its reply history is kept and you can restore it any time.');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="action-menu-item">
                                        <i class="fa-solid fa-box-archive"></i> Archive
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="cm-empty">
                    @if($search !== '' || $status !== 'all' || $notice !== '')
                        No messages match your search/filters.
                    @else
                        No contact messages yet. Inquiries from your website will appear here.
                    @endif
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
    </div><!-- /table-container -->

    <!-- ================= PAGINATION ================= -->
    <div class="pagination-wrapper">
        <div class="pagination-info">
            @if($messages->total() > 0)
                Showing <strong>{{ $messages->firstItem() }}–{{ $messages->lastItem() }}</strong>
                of <strong>{{ $messages->total() }}</strong> result{{ $messages->total() !== 1 ? 's' : '' }}
            @else
                No results found
            @endif
        </div>

        <nav class="pagination-nav" aria-label="Pagination">

            {{-- Previous --}}
            @if($messages->onFirstPage())
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-left"></i>
                </span>
            @else
                <a class="page-btn" href="{{ $messages->previousPageUrl() }}">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            @endif

            {{-- Page Numbers --}}
            @php
                $currentPage = $messages->currentPage();
                $lastPage    = $messages->lastPage();

                $pages = collect(range(1, $lastPage))->filter(function ($p) use ($currentPage, $lastPage) {
                    return $p === 1
                        || $p === $lastPage
                        || abs($p - $currentPage) <= 2;
                })->values();
            @endphp

            @php $prev = null; @endphp
            @foreach($pages as $page)
                @if($prev !== null && $page - $prev > 1)
                    <span class="page-ellipsis">…</span>
                @endif

                @if($page === $currentPage)
                    <span class="page-btn active">{{ $page }}</span>
                @else
                    <a class="page-btn" href="{{ $messages->url($page) }}">{{ $page }}</a>
                @endif

                @php $prev = $page; @endphp
            @endforeach

            {{-- Next --}}
            @if($messages->hasMorePages())
                <a class="page-btn" href="{{ $messages->nextPageUrl() }}">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            @else
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-right"></i>
                </span>
            @endif

        </nav>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ================= ACTION MENU (three-dot dropdown) =================
    // The dropdown is pinned with position:fixed to the toggle button so the
    // table container can never clip it.
    let openMenu = null;

    function closeActionMenu() {
        if (!openMenu) return;
        openMenu.dropdown.style.display = 'none';
        openMenu.dropdown.style.position = '';
        openMenu.dropdown.style.top = '';
        openMenu.dropdown.style.left = '';
        openMenu.toggle.setAttribute('aria-expanded', 'false');
        openMenu = null;
    }

    document.querySelectorAll('.action-menu-toggle').forEach(function (toggle) {
        const wrapper  = toggle.closest('.action-menu');
        const dropdown = wrapper.querySelector('.action-menu-dropdown');

        toggle.addEventListener('click', function (e) {
            e.stopPropagation();

            const alreadyOpenForThis = openMenu && openMenu.dropdown === dropdown;
            closeActionMenu();
            if (alreadyOpenForThis) return;

            const rect = toggle.getBoundingClientRect();
            const menuWidth = 170;

            dropdown.style.display = 'block';
            dropdown.style.position = 'fixed';
            dropdown.style.minWidth = menuWidth + 'px';

            let left = rect.right - menuWidth;
            if (left < 8) left = 8;
            if (left + menuWidth > window.innerWidth - 8) {
                left = window.innerWidth - menuWidth - 8;
            }

            const estimatedMenuHeight = dropdown.offsetHeight || 90;
            let top = rect.bottom + 6;
            if (top + estimatedMenuHeight > window.innerHeight - 8) {
                top = rect.top - estimatedMenuHeight - 6;
            }

            dropdown.style.top  = top + 'px';
            dropdown.style.left = left + 'px';

            toggle.setAttribute('aria-expanded', 'true');
            openMenu = { dropdown: dropdown, toggle: toggle };
        });
    });

    document.addEventListener('click', closeActionMenu);
    window.addEventListener('scroll', closeActionMenu, true);
    window.addEventListener('resize', closeActionMenu);

});
</script>

@endsection