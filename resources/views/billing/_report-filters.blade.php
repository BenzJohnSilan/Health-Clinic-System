{{-- Date range tabs shared by the Staff and Admin Revenue Report pages.
     Expects: $action (form URL), $reportRange. --}}
<form method="GET" action="{{ $action }}" class="billing-filters report-range-form" id="rangeForm">
    <div class="report-range-tabs">
        @foreach(['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'custom' => 'Custom'] as $key => $label)
            <label class="report-range-tab {{ $reportRange['range'] === $key ? 'active' : '' }}">
                <input type="radio" name="range" value="{{ $key }}" {{ $reportRange['range'] === $key ? 'checked' : '' }}
                       onchange="{{ $key === 'custom' ? "document.getElementById('customRange').style.display='flex'" : 'this.form.submit()' }}">
                {{ $label }}
            </label>
        @endforeach
    </div>

    <div class="report-range-custom" id="customRange" style="display: {{ $reportRange['range'] === 'custom' ? 'flex' : 'none' }};">
        <input type="date" name="from" class="billing-filter-select" value="{{ $reportRange['from'] }}">
        <span>to</span>
        <input type="date" name="to" class="billing-filter-select" value="{{ $reportRange['to'] }}">
        <button type="submit" class="btn-filter-apply">Apply</button>
    </div>

    <span class="report-range-label">Showing: <strong>{{ $reportRange['label'] }}</strong></span>
</form>
