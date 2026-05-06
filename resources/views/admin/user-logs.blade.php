@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-user-logs.css') }}">
@endsection

@section('content')
<div class="container">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h1 class="page-title">User Logs</h1>
    </div>

    <!-- ================= SEARCH / FILTER BAR ================= -->
    <div class="filter-bar">
        <form method="GET" action="{{ route('admin.user-logs') }}" class="filter-form">

            <div class="filter-group">
                <input
                    type="text"
                    name="search"
                    class="filter-input"
                    placeholder="Search user or action..."
                    value="{{ request('search') }}"
                >
            </div>

            <div class="filter-group">
                <input
                    type="date"
                    name="date"
                    class="filter-input"
                    value="{{ request('date') }}"
                >
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn-filter">Search</button>
                <a href="{{ route('admin.user-logs') }}" class="btn-reset">Reset</a>
            </div>

        </form>
    </div>

    <!-- ================= LOGS TABLE ================= -->
    <div class="table-container">
        <table class="logs-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td>
                        {{ $log->user->first_name ?? 'System' }}
                        {{ $log->user->last_name ?? '' }}
                    </td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->details ?? '-' }}</td>
                    <td>{{ $log->created_at->format('F j, Y g:i A') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align:center; font-style:italic; color:#555; padding:20px;">
                        No logs found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- ================= PAGINATION ================= -->
    @if($logs->hasPages())
    <div class="pagination-wrapper">
        <div class="pagination-info">
            Showing {{ $logs->firstItem() }}–{{ $logs->lastItem() }}
            of {{ $logs->total() }} results
        </div>
        <div class="pagination-links">
            {{-- Custom pagination buttons --}}
            <div class="pagination">

                {{-- Previous --}}
                @if($logs->onFirstPage())
                    <span class="page-item disabled">
                        <span class="page-link">&laquo;</span>
                    </span>
                @else
                    <a class="page-item" href="{{ $logs->appends(request()->query())->previousPageUrl() }}">
                        <span class="page-link">&laquo;</span>
                    </a>
                @endif

                {{-- Page Numbers --}}
                @foreach($logs->appends(request()->query())->getUrlRange(1, $logs->lastPage()) as $page => $url)
                    @if($page == $logs->currentPage())
                        <span class="page-item active">
                            <span class="page-link">{{ $page }}</span>
                        </span>
                    @else
                        <a class="page-item" href="{{ $url }}">
                            <span class="page-link">{{ $page }}</span>
                        </a>
                    @endif
                @endforeach

                {{-- Next --}}
                @if($logs->hasMorePages())
                    <a class="page-item" href="{{ $logs->appends(request()->query())->nextPageUrl() }}">
                        <span class="page-link">&raquo;</span>
                    </a>
                @else
                    <span class="page-item disabled">
                        <span class="page-link">&raquo;</span>
                    </span>
                @endif

            </div>
        </div>
    </div>
    @endif

</div>
@endsection