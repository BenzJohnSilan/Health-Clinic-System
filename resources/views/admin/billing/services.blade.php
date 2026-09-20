@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-billing.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endsection

@section('content')
<div class="container services-page">

    <div class="page-header">
        <h1 class="page-title">Services / Fees</h1>
        <button class="btn-add" onclick="openModal('addServiceModal')">
            <i class="fa-solid fa-plus"></i> Add Service
        </button>
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert-error">
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="table-container">
        <table class="patients-table">
            <thead>
                <tr>
                    <th>Service Name</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($services as $service)
                <tr>
                    <td>{{ $service->name }}</td>
                    <td>{{ $service->description ?: '—' }}</td>
                    <td>₱{{ number_format($service->price, 2) }}</td>
                    <td>
                        <span class="status-badge {{ $service->is_active ? 'status-badge--paid' : 'status-badge--cancelled' }}">
                            {{ $service->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>
                        <button class="btn-view"
                            onclick="openEditModal({{ $service->id }}, '{{ addslashes($service->name) }}', '{{ addslashes($service->description) }}', '{{ $service->price }}', {{ $service->is_active ? 1 : 0 }})">
                            Edit
                        </button>
                        <form action="{{ route('admin.services.destroy', $service->id) }}" method="POST" style="display:inline;"
                              onsubmit="return confirm('Delete this service?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-delete">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="empty-row">No services configured yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

<!-- ================= ADD SERVICE MODAL ================= -->
<div id="addServiceModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <button class="modal-close-btn" onclick="closeModal('addServiceModal')" aria-label="Close">&times;</button>
        <h3 class="modal-title">Add Service</h3>
        <form method="POST" action="{{ route('admin.services.store') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Service Name <span class="required">*</span></label>
                <input class="form-input" type="text" name="name" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-input" name="description" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Price (₱) <span class="required">*</span></label>
                <input class="form-input" type="number" step="0.01" min="0" name="price" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-back" onclick="closeModal('addServiceModal')">Cancel</button>
                <button type="submit" class="btn-confirm">Save Service</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= EDIT SERVICE MODAL ================= -->
<div id="editServiceModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <button class="modal-close-btn" onclick="closeModal('editServiceModal')" aria-label="Close">&times;</button>
        <h3 class="modal-title">Edit Service</h3>
        <form method="POST" id="editServiceForm">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label class="form-label">Service Name <span class="required">*</span></label>
                <input class="form-input" type="text" name="name" id="edit_name" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-input" name="description" id="edit_description" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Price (₱) <span class="required">*</span></label>
                <input class="form-input" type="number" step="0.01" min="0" name="price" id="edit_price" required>
            </div>
            <div class="form-group">
                <label class="form-label">
                    <input type="checkbox" name="is_active" id="edit_is_active" value="1"> Active (visible to Doctors for billing)
                </label>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-back" onclick="closeModal('editServiceModal')">Cancel</button>
                <button type="submit" class="btn-confirm">Update Service</button>
            </div>
        </form>
    </div>
</div>

<script>
window.openModal  = function (id) { document.getElementById(id).style.display = 'flex'; };
window.closeModal = function (id) { document.getElementById(id).style.display = 'none'; };

document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
    overlay.addEventListener('click', function (e) {
        if (e.target === this) closeModal(this.id);
    });
});

window.openEditModal = function (id, name, description, price, isActive) {
    document.getElementById('editServiceForm').action = '/admin/services/' + id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_is_active').checked = !!isActive;
    openModal('editServiceModal');
};

@if($errors->any())
    openModal('addServiceModal');
@endif
</script>
@endsection
