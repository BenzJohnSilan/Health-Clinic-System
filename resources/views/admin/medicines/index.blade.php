@extends('layouts.admin')
@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-medicines.css') }}">
@endsection

@section('content')

<div class="container">

    <!-- HEADER -->
    <div class="page-header">
        <h2>Medicine Inventory</h2>
        <div style="display:flex; gap:10px;">
            <button class="btn-export" onclick="exportCSV()">⬇ Export CSV</button>
            <button class="btn-add" onclick="openModal()">+ Add Medicine</button>
        </div>
    </div>

    <!-- SUCCESS MESSAGE -->
    @if(session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <!-- EXPIRING SOON BANNER -->
    @php
        $expiringSoon = $medicines->filter(function($m) {
            $expDate  = \Carbon\Carbon::parse($m->expiration_date)->startOfDay();
            $today    = \Carbon\Carbon::today();
            $daysLeft = $today->diffInDays($expDate, false); // signed: negative = already past
            return !$m->is_expired && $daysLeft >= 0 && $daysLeft <= 30;
        });
    @endphp

    @if($expiringSoon->count() > 0)
        <div class="alert-warning">
            ⚠ <strong>{{ $expiringSoon->count() }} medicine(s)</strong> will expire within 30 days:
            {{ $expiringSoon->pluck('medicine_name')->join(', ') }}.
        </div>
    @endif

    <!-- SEARCH & FILTER -->
    <div class="search-filter-bar">
        <input
            type="text"
            id="searchInput"
            placeholder="🔍  Search medicine name or brand..."
            oninput="applyFilters()"
        >
        <div class="filter-selects">
            <select id="filterCategory" onchange="applyFilters()">
                <option value="">All Categories</option>
                <option value="Antibiotic">Antibiotic</option>
                <option value="Painkiller">Painkiller</option>
                <option value="Vitamin">Vitamin</option>
                <option value="Antiviral">Antiviral</option>
                <option value="Antihistamine">Antihistamine</option>
                <option value="Others">Others</option>
            </select>
            <select id="filterStatus" onchange="applyFilters()">
                <option value="">All Status</option>
                <option value="Available">Available</option>
                <option value="Low Stock">Low Stock</option>
                <option value="Out of Stock">Out of Stock</option>
                <option value="Expired">Expired</option>
            </select>
            <button class="btn-clear" onclick="clearFilters()">✕ Clear</button>
        </div>
    </div>

    <!-- TABLE -->
    <div class="table-container">
        <table class="medicine-table" id="medicineTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Medicine Name</th>
                    <th>Brand</th>
                    <th>Category</th>
                    <th>Dosage</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Price</th>
                    <th>Total Value</th>
                    <th>Expiration Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody id="medicineTableBody">
                @forelse($medicines as $index => $medicine)
                @php
                    $statusLabel = $medicine->is_expired ? 'Expired'
                        : ($medicine->status === 'Available' ? 'Available'
                        : ($medicine->status === 'Low Stock' ? 'Low Stock' : 'Out of Stock'));
                @endphp
                <tr
                    data-name="{{ strtolower($medicine->medicine_name) }}"
                    data-brand="{{ strtolower($medicine->brand) }}"
                    data-category="{{ $medicine->category }}"
                    data-status="{{ $statusLabel }}"
                >
                    <td class="row-num">{{ $index + 1 }}</td>
                    <td>{{ $medicine->medicine_name }}</td>
                    <td>{{ $medicine->brand }}</td>
                    <td>{{ $medicine->category }}</td>
                    <td>{{ $medicine->dosage }}</td>
                    <td>{{ $medicine->quantity }}</td>
                    <td>{{ $medicine->unit }}</td>
                    <td>₱{{ number_format($medicine->price, 2) }}</td>
                    <td>₱{{ number_format($medicine->price * $medicine->quantity, 2) }}</td>
                    <td>{{ \Carbon\Carbon::parse($medicine->expiration_date)->format('M d, Y') }}</td>

                    {{-- Status --}}
                    <td>
                        @if($medicine->is_expired)
                            <span class="badge out-of-stock">Expired</span>
                        @elseif($medicine->status === 'Available')
                            <span class="badge available">Available</span>
                        @elseif($medicine->status === 'Low Stock')
                            <span class="badge low-stock">Low Stock</span>
                        @else
                            <span class="badge out-of-stock">Out of Stock</span>
                        @endif
                    </td>

                    {{-- Action --}}
                    <td class="action-buttons">
                        <button
                            class="btn-edit"
                            onclick="openEditModal(
                                {{ $medicine->id }},
                                '{{ addslashes($medicine->medicine_name) }}',
                                '{{ addslashes($medicine->brand) }}',
                                '{{ $medicine->category }}',
                                '{{ addslashes($medicine->dosage) }}',
                                {{ $medicine->quantity }},
                                '{{ $medicine->unit }}',
                                {{ $medicine->price }},
                                '{{ $medicine->expiration_date }}'
                            )">
                            Edit
                        </button>

                        <form action="{{ route('admin.medicines.destroy', $medicine->id) }}" method="POST"
                            onsubmit="return confirm('Delete this medicine?')" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-delete">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="12" style="text-align:center; padding:20px; color:#888;">
                        No medicines in inventory yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div id="noResults" style="display:none; text-align:center; padding:20px; color:#888;">
            No medicines matched your search.
        </div>
    </div>

    <!-- PAGINATION -->
    <div class="pagination-bar" id="paginationBar"></div>

</div>

<!-- ================= ADD MODAL ================= -->
<div id="medicineModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Add Medicine</h3>
        <form action="{{ route('admin.medicines.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label>Medicine Name</label>
                <input type="text" name="medicine_name" required>
            </div>
            <div class="form-group">
                <label>Brand</label>
                <input type="text" name="brand" required>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category" required>
                    <option value="">-- Select Category --</option>
                    <option value="Antibiotic">Antibiotic</option>
                    <option value="Painkiller">Painkiller</option>
                    <option value="Vitamin">Vitamin</option>
                    <option value="Antiviral">Antiviral</option>
                    <option value="Antihistamine">Antihistamine</option>
                    <option value="Others">Others</option>
                </select>
            </div>
            <div class="form-group">
                <label>Dosage</label>
                <input type="text" name="dosage" placeholder="e.g. 500mg" required>
            </div>
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="quantity" min="0" required>
            </div>
            <div class="form-group">
                <label>Unit</label>
                <select name="unit" required>
                    <option value="">-- Select Unit --</option>
                    <option value="tablets">Tablets</option>
                    <option value="capsules">Capsules</option>
                    <option value="ml">ml</option>
                    <option value="ampules">Ampules</option>
                    <option value="sachets">Sachets</option>
                </select>
            </div>
            <div class="form-group">
                <label>Price</label>
                <input type="number" name="price" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label>Expiration Date</label>
                <input type="date" name="expiration_date" required>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeModal()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-save">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= EDIT MODAL ================= -->
<div id="editMedicineModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Edit Medicine</h3>
        <form id="editMedicineForm" action="" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label>Medicine Name</label>
                <input type="text" name="medicine_name" id="edit_medicine_name" required>
            </div>
            <div class="form-group">
                <label>Brand</label>
                <input type="text" name="brand" id="edit_brand" required>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category" id="edit_category" required>
                    <option value="">-- Select Category --</option>
                    <option value="Antibiotic">Antibiotic</option>
                    <option value="Painkiller">Painkiller</option>
                    <option value="Vitamin">Vitamin</option>
                    <option value="Antiviral">Antiviral</option>
                    <option value="Antihistamine">Antihistamine</option>
                    <option value="Others">Others</option>
                </select>
            </div>
            <div class="form-group">
                <label>Dosage</label>
                <input type="text" name="dosage" id="edit_dosage" placeholder="e.g. 500mg" required>
            </div>
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="quantity" id="edit_quantity" min="0" required>
            </div>
            <div class="form-group">
                <label>Unit</label>
                <select name="unit" id="edit_unit" required>
                    <option value="">-- Select Unit --</option>
                    <option value="tablets">Tablets</option>
                    <option value="capsules">Capsules</option>
                    <option value="ml">ml</option>
                    <option value="ampules">Ampules</option>
                    <option value="sachets">Sachets</option>
                </select>
            </div>
            <div class="form-group">
                <label>Price</label>
                <input type="number" name="price" id="edit_price" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label>Expiration Date</label>
                <input type="date" name="expiration_date" id="edit_expiration_date" required>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeEditModal()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-save">Update</button>
            </div>
        </form>
    </div>
</div>

@endsection

<script>
const ROWS_PER_PAGE = 10;
let currentPage  = 1;
let filteredRows = [];

function getAllRows() {
    return Array.from(document.querySelectorAll('#medicineTableBody tr[data-name]'));
}

function applyFilters() {
    const search   = document.getElementById('searchInput').value.toLowerCase();
    const category = document.getElementById('filterCategory').value;
    const status   = document.getElementById('filterStatus').value;

    filteredRows = getAllRows().filter(row => {
        const matchSearch   = row.dataset.name.includes(search) || row.dataset.brand.includes(search);
        const matchCategory = !category || row.dataset.category === category;
        const matchStatus   = !status   || row.dataset.status === status;
        return matchSearch && matchCategory && matchStatus;
    });

    currentPage = 1;
    renderPage();
}

function renderPage() {
    getAllRows().forEach(r => r.style.display = 'none');

    const start    = (currentPage - 1) * ROWS_PER_PAGE;
    const pageRows = filteredRows.slice(start, start + ROWS_PER_PAGE);

    pageRows.forEach((row, i) => {
        row.style.display = '';
        const numCell = row.querySelector('.row-num');
        if (numCell) numCell.textContent = start + i + 1;
    });

    document.getElementById('noResults').style.display = filteredRows.length === 0 ? 'block' : 'none';
    renderPagination();
}

function renderPagination() {
    const bar        = document.getElementById('paginationBar');
    const totalPages = Math.ceil(filteredRows.length / ROWS_PER_PAGE);
    bar.innerHTML    = '';

    if (totalPages <= 1) return;

    const prev     = document.createElement('button');
    prev.textContent = '← Prev';
    prev.className   = 'page-btn';
    prev.disabled    = currentPage === 1;
    prev.onclick     = () => { currentPage--; renderPage(); };
    bar.appendChild(prev);

    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement('button');
        btn.textContent = i;
        btn.className   = 'page-btn' + (i === currentPage ? ' active' : '');
        btn.onclick     = () => { currentPage = i; renderPage(); };
        bar.appendChild(btn);
    }

    const next     = document.createElement('button');
    next.textContent = 'Next →';
    next.className   = 'page-btn';
    next.disabled    = currentPage === totalPages;
    next.onclick     = () => { currentPage++; renderPage(); };
    bar.appendChild(next);
}

function clearFilters() {
    document.getElementById('searchInput').value    = '';
    document.getElementById('filterCategory').value = '';
    document.getElementById('filterStatus').value   = '';
    applyFilters();
}

window.addEventListener('DOMContentLoaded', () => {
    filteredRows = getAllRows();
    renderPage();
});

// Export CSV
function exportCSV() {
    const headers = ['#','Medicine Name','Brand','Category','Dosage','Quantity','Unit','Price','Total Value','Expiration Date','Status'];
    const rows = filteredRows.map((row, i) => {
        const cells = row.querySelectorAll('td');
        return [
            i + 1,
            cells[1].textContent.trim(),
            cells[2].textContent.trim(),
            cells[3].textContent.trim(),
            cells[4].textContent.trim(),
            cells[5].textContent.trim(),
            cells[6].textContent.trim(),
            cells[7].textContent.trim(),
            cells[8].textContent.trim(),
            cells[9].textContent.trim(),
            cells[10].textContent.trim(),
        ].map(v => `"${v}"`).join(',');
    });

    const csv  = [headers.join(','), ...rows].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'medicine_inventory.csv';
    a.click();
    URL.revokeObjectURL(url);
}

// Add Modal
function openModal()  { document.getElementById('medicineModal').style.display = 'flex'; }
function closeModal() { document.getElementById('medicineModal').style.display = 'none'; }

// Edit Modal
function openEditModal(id, name, brand, category, dosage, quantity, unit, price, expiration_date) {
    document.getElementById('editMedicineForm').action        = `/admin/medicines/${id}`;
    document.getElementById('edit_medicine_name').value       = name;
    document.getElementById('edit_brand').value               = brand;
    document.getElementById('edit_category').value            = category;
    document.getElementById('edit_dosage').value              = dosage;
    document.getElementById('edit_quantity').value            = quantity;
    document.getElementById('edit_unit').value                = unit;
    document.getElementById('edit_price').value               = price;
    document.getElementById('edit_expiration_date').value     = expiration_date;
    document.getElementById('editMedicineModal').style.display = 'flex';
}
function closeEditModal() { document.getElementById('editMedicineModal').style.display = 'none'; }

// Close on outside click
window.onclick = function(e) {
    ['medicineModal','editMedicineModal'].forEach(id => {
        const modal = document.getElementById(id);
        if (e.target === modal) modal.style.display = 'none';
    });
}
</script>