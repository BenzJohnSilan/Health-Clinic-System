@props(['name'])

@if($errors->has($name))
    <div class="field-error" id="{{ $name }}-error" role="alert">
        @foreach($errors->get($name) as $message)
            <div><i class='bx bx-error-circle'></i> <span>{{ $message }}</span></div>
        @endforeach
    </div>
@endif