{{-- Four headline cards (screen only). Expects $report, $labels. --}}
@php
    $peso = fn ($n) => '₱' . number_format((float) $n, 2);
    $cats = $report['categories'];
@endphp
<div class="ab-stats-grid ab-stats-grid--four">
    <div class="ab-stat-card ab-stat-card--purple">
        <div class="ab-stat-icon"><i class="fa-solid fa-chart-line"></i></div>
        <div><p>Total Net Sales</p><h3>{{ $peso($report['totals']['net']) }}</h3></div>
    </div>
    <div class="ab-stat-card ab-stat-card--green">
        <div class="ab-stat-icon"><i class="fa-solid fa-stethoscope"></i></div>
        <div><p>Consultation</p><h3>{{ $peso($cats['consultation']['net']) }}</h3></div>
    </div>
    <div class="ab-stat-card ab-stat-card--blue">
        <div class="ab-stat-icon"><i class="fa-solid fa-file-medical"></i></div>
        <div><p>Medical Certificate</p><h3>{{ $peso($cats['medical_certificate']['net']) }}</h3></div>
    </div>
    <div class="ab-stat-card ab-stat-card--red">
        <div class="ab-stat-icon"><i class="fa-solid fa-pills"></i></div>
        <div><p>Medicine</p><h3>{{ $peso($cats['medicine']['net']) }}</h3></div>
    </div>
</div>