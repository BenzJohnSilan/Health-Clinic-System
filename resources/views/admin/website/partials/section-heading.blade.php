{{-- Heading form for a repeatable section. Vars: group, settings --}}
<form method="POST" action="{{ route('admin.website.settings.update', $group) }}" class="ws-card" data-ws-form>
    @csrf @method('PUT')
    <div class="ws-card-head">
        <div class="ws-card-title"><span class="ws-card-icon"><i class='bx bx-text'></i></span><h3>Section heading</h3></div>
    </div>
    <div class="ws-grid-3">
        @include('admin.website.partials.field', ['name' => $group.'_label', 'label' => 'Small label', 'max' => 60, 'required' => true])
        @include('admin.website.partials.field', ['name' => $group.'_title', 'label' => 'Heading', 'max' => 120, 'required' => true])
        @include('admin.website.partials.field', ['name' => $group.'_sub', 'label' => 'Subtitle', 'max' => 300])
    </div>
    <div class="ws-actions">
        <button type="submit" class="ws-btn ws-btn-primary"><i class='bx bx-save'></i> Save heading</button>
        <a href="{{ route('admin.website.index', ['tab' => $group]) }}" class="ws-btn ws-btn-ghost">Cancel</a>
    </div>
</form>