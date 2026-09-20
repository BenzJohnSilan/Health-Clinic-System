@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-user-logs.css') }}">
@endsection

@section('content')

<div class="container">

    {{-- ================= HEADER ================= --}}
    <div class="page-header">
        <h1>User Logs</h1>
    </div>

    {{-- ================= FILTER BAR ================= --}}
    <div class="filter-bar">
        <form method="GET" action="{{ route('admin.user-logs') }}">

            <div class="filter-grid">

                {{-- Search --}}
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input
                        type="text"
                        id="search"
                        name="search"
                        placeholder="Search name, username, action, details..."
                        value="{{ request('search') }}"
                    >
                </div>

                {{-- Role --}}
                <div class="filter-group">
                    <label for="role">Role</label>
                    <select id="role" name="role">
                        <option value="">All Roles</option>
                        @foreach($roles as $r)
                            <option value="{{ $r }}" {{ request('role') === $r ? 'selected' : '' }}>
                                {{ $r }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Module --}}
                <div class="filter-group">
                    <label for="module">Module</label>
                    <select id="module" name="module">
                        <option value="">All Modules</option>
                        @foreach($modules as $mod)
                            <option value="{{ $mod }}" {{ request('module') === $mod ? 'selected' : '' }}>
                                {{ $mod }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Date From --}}
                <div class="filter-group">
                    <label for="date_from">Date From</label>
                    <input
                        type="date"
                        id="date_from"
                        name="date_from"
                        value="{{ request('date_from') }}"
                    >
                </div>

                {{-- Date To --}}
                <div class="filter-group">
                    <label for="date_to">Date To</label>
                    <input
                        type="date"
                        id="date_to"
                        name="date_to"
                        value="{{ request('date_to') }}"
                    >
                </div>

            </div>

            <div class="filter-actions">
                <a href="{{ route('admin.user-logs') }}" class="btn-filter btn-filter-reset">
                    <i class="fa-solid fa-rotate-left"></i>
                    Reset
                </a>
                <button type="submit" class="btn-filter btn-filter-apply">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Filter
                </button>
            </div>

        </form>
    </div>

    {{-- ================= ACTIVE FILTER TAGS ================= --}}
    @php
        $activeFilters = array_filter([
            'search'    => request('search'),
            'role'      => request('role'),
            'module'    => request('module'),
            'date_from' => request('date_from'),
            'date_to'   => request('date_to'),
        ]);
    @endphp

    @if(count($activeFilters))
        <div class="active-filters">
            <span class="active-filters-label">Filters:</span>

            @foreach($activeFilters as $key => $value)
                @php
                    $label = match($key) {
                        'search'    => 'Keyword: ' . $value,
                        'role'      => 'Role: ' . $value,
                        'module'    => 'Module: ' . $value,
                        'date_from' => 'From: ' . $value,
                        'date_to'   => 'To: ' . $value,
                        default     => $value,
                    };
                    $removeParams = request()->except($key);
                    $removeUrl    = route('admin.user-logs') . '?' . http_build_query($removeParams);
                @endphp

                <span class="filter-tag">
                    {{ $label }}
                    <a href="{{ $removeUrl }}" title="Remove filter">&#x2715;</a>
                </span>
            @endforeach
        </div>
    @endif

    {{-- ================= TABLE ================= --}}
    <div class="table-wrapper">

        <table class="medical-table">

            <thead>
                <tr>
                    <th>Date &amp; Time</th>
                    <th>User / Actor</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Details</th>
                </tr>
            </thead>

            <tbody>

                @forelse($logs as $log)
                    <tr>

                        <td class="log-datetime">{{ $log->created_at->format('M d, Y') }}<br>{{ $log->created_at->format('h:i A') }}</td>

                        {{-- User / Actor — always the account that performed the action,
                             never the patient/doctor/user the action was performed on. --}}
                        <td class="log-actor">
                            @if($log->user)
                                <span class="log-actor-name">{{ $log->user->full_name }}</span>
                                <span class="log-actor-username">{{ '@' . $log->user->username }}</span>
                            @elseif(is_null($log->user_id))
                                <span class="log-actor-deleted">System</span>
                            @else
                                <span class="log-actor-deleted">Deleted User</span>
                            @endif
                        </td>

                        <td>
                            @if($log->user && $log->user->role)
                                <span class="log-role-badge {{ strtolower($log->user->role) }}">
                                    {{ $log->user->role }}
                                </span>
                            @else
                                —
                            @endif
                        </td>

                        <td>
                            <span class="log-action-badge {{ strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $log->action), '-')) }}">
                                <i class='bx bx-history'></i>
                                {{ $log->action }}
                            </span>
                        </td>

                        <td>
                            @if($log->module)
                                <span class="log-module-badge {{ strtolower(str_replace([' & ', ' '], ['-', '-'], $log->module)) }}">
                                    {{ $log->module }}
                                </span>
                            @else
                                —
                            @endif
                        </td>

                        <td class="log-details">{{ $log->details ?? '—' }}</td>

                    </tr>
                @empty

                    <tr>
                        <td colspan="6" class="no-data">
                            No logs found.
                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

    {{-- ================= PAGINATION ================= --}}
    <div class="pagination-wrapper">

        <div class="pagination-info">
            @if($logs->total() > 0)
                Showing <strong>{{ $logs->firstItem() }}–{{ $logs->lastItem() }}</strong>
                of <strong>{{ $logs->total() }}</strong> result{{ $logs->total() !== 1 ? 's' : '' }}
            @else
                No results found
            @endif
        </div>

        <nav class="pagination-nav" aria-label="Pagination">

            {{-- Previous --}}
            @if($logs->onFirstPage())
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-left"></i>
                </span>
            @else
                <a class="page-btn" href="{{ $logs->previousPageUrl() }}">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            @endif

            {{-- Page Numbers --}}
            @php
                $currentPage = $logs->currentPage();
                $lastPage    = $logs->lastPage();

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
                    <a class="page-btn" href="{{ $logs->url($page) }}">
                        {{ $page }}
                    </a>
                @endif

                @php $prev = $page; @endphp
            @endforeach

            {{-- Next --}}
            @if($logs->hasMorePages())
                <a class="page-btn" href="{{ $logs->nextPageUrl() }}">
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

@endsection
