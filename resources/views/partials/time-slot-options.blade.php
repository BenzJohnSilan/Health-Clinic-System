{{--
    Time-slot <option>s, generated from Admin > System Settings
    (App\Services\ClinicSchedule). Optional var: $selected (e.g. old value).
    The lunch row keeps data-lunch="true" + disabled so the existing
    availability JavaScript treats it exactly as before.
--}}
@foreach(\App\Services\ClinicSchedule::slots() as $__slot)
    @if($__slot['lunch'])
        <option value="{{ $__slot['value'] }}" data-lunch="true" disabled>{{ $__slot['label'] }}</option>
    @else
        <option value="{{ $__slot['value'] }}" {{ ($selected ?? null) === $__slot['value'] ? 'selected' : '' }}>{{ $__slot['label'] }}</option>
    @endif
@endforeach
