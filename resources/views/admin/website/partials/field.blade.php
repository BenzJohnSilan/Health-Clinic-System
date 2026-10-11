{{-- Reusable settings field. Vars: name, label, type (text|textarea), rows, max, help, required --}}
@php
    $type     = $type ?? 'text';
    $required = $required ?? false;
@endphp
<div class="ws-field">
    <label for="f-{{ $name }}">{{ $label }}@if($required) <span class="ws-req">*</span>@endif</label>
    @if($type === 'textarea')
        <textarea id="f-{{ $name }}" name="{{ $name }}" rows="{{ $rows ?? 3 }}"
                  maxlength="{{ $max ?? 1000 }}" @if($required) required @endif
                  class="@error($name) is-invalid @enderror">{{ old($name, $settings[$name] ?? '') }}</textarea>
    @else
        <input id="f-{{ $name }}" type="text" name="{{ $name }}"
               value="{{ old($name, $settings[$name] ?? '') }}"
               maxlength="{{ $max ?? 255 }}" @if($required) required @endif
               class="@error($name) is-invalid @enderror">
    @endif
    @isset($help)<small class="ws-help">{{ $help }}</small>@endisset
    @error($name)<small class="ws-error">{{ $message }}</small>@enderror
</div>
