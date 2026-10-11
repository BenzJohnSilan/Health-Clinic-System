{{-- Read-only Vital Signs (recorded by Staff). Expects $medicalRecord (may be null). --}}
@php
    $vitalRows = ($medicalRecord ?? null)
        ? $medicalRecord->vitalsForDisplay()
        : (new \App\Models\MedicalRecord())->vitalsForDisplay();

    $vitalIcons = [
        'blood_pressure'    => ['sa-vital-icon--bp',     'bx-pulse'],
        'temperature'       => ['sa-vital-icon--temp',   'bxs-thermometer'],
        'pulse_rate'        => ['sa-vital-icon--height', 'bx-heart'],
        'respiratory_rate'  => ['sa-vital-icon--height', 'bx-wind'],
        'oxygen_saturation' => ['sa-vital-icon--height', 'bx-droplet'],
        'weight'            => ['sa-vital-icon--weight', 'bx-body'],
        'height'            => ['sa-vital-icon--height', 'bx-ruler'],
    ];
    $anyRecorded = collect($vitalRows)->contains(fn ($r) => $r['status'] !== 'empty');
@endphp

<div class="sa-vitals-grid sa-vitals-grid--readonly">
    @foreach($vitalRows as $key => $row)
        <div class="sa-vital-tile {{ $row['status'] === 'recorded' ? '' : 'sa-vital-tile--missing' }}">
            <div class="sa-vital-icon {{ $vitalIcons[$key][0] }}">
                <i class='bx {{ $vitalIcons[$key][1] }}'></i>
            </div>
            <div class="sa-vital-body">
                <div class="sa-vital-label">{{ $row['label'] }}</div>
                @if($row['status'] === 'recorded')
                    <div class="sa-vital-val">{{ $row['value'] }}@if($row['unit']) <span class="sa-vital-unit">{{ $row['unit'] }}</span>@endif</div>
                @elseif($row['status'] === 'not_taken')
                    <div class="sa-vital-val sa-vital-val--missing">Not taken</div>
                    <div class="sa-vital-reason">{{ $row['reason'] }}</div>
                @else
                    <div class="sa-vital-val sa-vital-val--missing">Not recorded</div>
                @endif
            </div>
        </div>
    @endforeach
</div>

<p class="sa-vitals-note">
    @if(($medicalRecord ?? null) && $medicalRecord->vitals_recorded_at)
        Recorded by Staff{{ $medicalRecord->vitalsRecordedBy ? ' (' . $medicalRecord->vitalsRecordedBy->first_name . ')' : '' }}
        · {{ $medicalRecord->vitals_recorded_at->format('M d, Y h:i A') }}
    @elseif(!$anyRecorded)
        No Vital Signs were recorded by Staff for this appointment. You can still continue the consultation.
    @else
        Recorded earlier in the consultation record.
    @endif
</p>