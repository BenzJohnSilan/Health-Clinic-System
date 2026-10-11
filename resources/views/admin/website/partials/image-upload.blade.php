{{-- Vars: name, label, current (url|null) --}}
<div class="ws-field">
    <label for="f-{{ $name }}">{{ $label }}</label>
    <div class="ws-image-preview" id="prev-{{ $name }}" @if(!$current) hidden @endif>
        <img src="{{ $current }}" alt="Current image">
    </div>
    <input type="file" id="f-{{ $name }}" name="{{ $name }}" accept="image/jpeg,image/png,image/webp"
           data-preview="#prev-{{ $name }}" class="@error($name) is-invalid @enderror">
    <small class="ws-help">JPG, PNG or WebP, up to 2 MB. Uploading a new picture replaces the old one.</small>
    @error($name)<small class="ws-error">{{ $message }}</small>@enderror
    @if($current)
        <label class="ws-check"><input type="checkbox" name="remove_{{ $name }}" value="1"> Remove current image</label>
    @endif
</div>
