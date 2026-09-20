@extends('layouts.' . $routePrefix)

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-medicines.css') }}">
<link rel="stylesheet" href="{{ asset('css/medicine-inventory-actions.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endsection

@section('content')

<div class="container">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h2>Medicine Inventory</h2>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button class="btn-export" onclick="exportCSV()">⬇ Export CSV</button>
            @if($canViewReports)
                <a href="{{ route($routePrefix . '.medicines.reports') }}" class="btn-export">
                    <i class="fa-solid fa-chart-column"></i> Reports
                </a>
            @endif
            @if($canAdd)
                <button class="btn-add" onclick="openModal()">+ Add Medicine</button>
            @endif
        </div>
    </div>

    <!-- ================= ALERTS ================= -->
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert-error">
            <strong>Please fix the following before continuing:</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- ================= EXPIRING SOON BANNER ================= -->
    @php
        $expiringSoon = $medicines->filter(function($m) {
            $expDate  = \Carbon\Carbon::parse($m->expiration_date)->startOfDay();
            $today    = \Carbon\Carbon::today();
            $daysLeft = $today->diffInDays($expDate, false);
            return !$m->is_expired && $daysLeft >= 0 && $daysLeft <= 30;
        });
    @endphp

    @if($expiringSoon->count() > 0)
        <div class="alert-warning">
            ⚠ <strong>{{ $expiringSoon->count() }} medicine(s)</strong> will expire within 30 days:
            {{ $expiringSoon->pluck('medicine_name')->join(', ') }}.
        </div>
    @endif

    <!-- ================= SEARCH & FILTER ================= -->
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

    <!-- ================= TABLE ================= -->
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
                    data-id="{{ $medicine->id }}"
                    data-name="{{ strtolower($medicine->medicine_name) }}"
                    data-brand="{{ strtolower($medicine->brand) }}"
                    data-category="{{ $medicine->category }}"
                    data-status="{{ $statusLabel }}"
                    data-medicine-name="{{ $medicine->medicine_name }}"
                    data-brand-name="{{ $medicine->brand }}"
                    data-dosage="{{ $medicine->dosage }}"
                    data-quantity="{{ $medicine->quantity }}"
                    data-unit="{{ $medicine->unit }}"
                    data-price="{{ $medicine->price }}"
                    data-expiration-date="{{ $medicine->expiration_date?->format('Y-m-d') }}"
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

                    {{-- Expiration Date: a single active batch shows its date directly;
                         multiple active batches show a clickable "N Batches ›" link
                         instead of collapsing them into one (possibly wrong) date. --}}
                    @php
                        $activeBatchList = $medicine->batches->where('quantity', '>', 0)->values();
                    @endphp
                    <td>
                        @if($activeBatchList->count() === 0)
                            <span class="muted">No Active Batch</span>
                        @elseif($activeBatchList->count() === 1)
                            {{ $activeBatchList->first()->expiration_date->format('M d, Y') }}
                        @else
                            <button type="button" class="batch-link" onclick="viewBatches({{ $medicine->id }})">
                                {{ $activeBatchList->count() }} Batches ›
                            </button>
                        @endif
                    </td>

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

                    {{-- Action: compact [ View ] [ ⋮ ] design --}}
                    <td class="action-buttons">
                        <button type="button" class="btn-view" onclick="viewMedicine(this)">View</button>
                        @if($canEdit || $canStockIn || $canStockOut || $canAdjustStock || $canViewStockHistory || $canDelete)
                            <button
                                type="button"
                                class="btn-kebab"
                                onclick="toggleKebab(event, this)"
                                aria-haspopup="true"
                                aria-expanded="false"
                                aria-label="More actions for {{ $medicine->medicine_name }}"
                            >
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="12" class="no-data">No medicines in inventory yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div id="noResults" style="display:none; text-align:center; padding:24px; color:#9ca3af; font-style:italic; background:#fff;">
            No medicines matched your search.
        </div>
    </div>

    <!-- ================= PAGINATION ================= -->
    <div class="pagination-wrapper">
        <div class="pagination-info" id="paginationInfo">&nbsp;</div>
        <nav class="pagination-nav" id="paginationNav" aria-label="Pagination"></nav>
    </div>

</div>

<!-- ================= SHARED KEBAB MENU (single floating instance, repositioned per row) ================= -->
@if($canEdit || $canStockIn || $canStockOut || $canAdjustStock || $canViewStockHistory || $canDelete)
<div id="kebabMenu" class="kebab-menu" role="menu" style="display:none;">
    @if($canEdit)
        <button type="button" class="kebab-item" role="menuitem" data-action="edit">
            <i class="fa-solid fa-pen"></i> Edit Medicine
        </button>
    @endif
    @if($canStockIn)
        <button type="button" class="kebab-item" role="menuitem" data-action="stock-in">
            <i class="fa-solid fa-circle-plus"></i> Stock In
        </button>
    @endif
    @if($canStockOut)
        <button type="button" class="kebab-item" role="menuitem" data-action="stock-out">
            <i class="fa-solid fa-circle-minus"></i> Stock Out
        </button>
    @endif
    @if($canAdjustStock)
        <button type="button" class="kebab-item" role="menuitem" data-action="adjust-stock">
            <i class="fa-solid fa-sliders"></i> Adjust Stock
        </button>
    @endif
    <button type="button" class="kebab-item" role="menuitem" data-action="view-batches">
        <i class="fa-solid fa-layer-group"></i> View Batches
    </button>
    @if($canViewStockHistory)
        <button type="button" class="kebab-item" role="menuitem" data-action="stock-history">
            <i class="fa-solid fa-clock-rotate-left"></i> Stock History
        </button>
    @endif
    @if($canDelete)
        <button type="button" class="kebab-item kebab-item-danger" role="menuitem" data-action="delete">
            <i class="fa-solid fa-trash"></i> Delete Medicine
        </button>
    @endif
</div>
@endif

<!-- ================= VIEW DETAILS MODAL (read-only) ================= -->
<div id="viewMedicineModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Medicine Details</h3>
        <div id="viewMedicineBody" class="view-details-grid">
            <div class="view-details-loading">Loading…</div>
        </div>
        <div class="modal-actions">
            <button type="button" onclick="closeViewModal()" class="btn-cancel">Close</button>
        </div>
    </div>
</div>

<!-- ================= BATCH VIEW MODAL (read-only list of all batches) ================= -->
<div id="batchViewModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3 id="batchViewTitle">Batches</h3>
        <div class="table-container">
            <table class="medicine-table" id="batchViewTable">
                <thead>
                    <tr>
                        <th>Batch/Lot No.</th>
                        <th>Expiration Date</th>
                        <th>Quantity</th>
                        <th>Batch Status</th>
                        @if($canEdit)
                            <th>Action</th>
                        @endif
                    </tr>
                </thead>
                <tbody id="batchViewBody">
                    <tr><td colspan="{{ $canEdit ? 5 : 4 }}" class="no-data">Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="modal-actions">
            <button type="button" onclick="closeBatchViewModal()" class="btn-cancel">Close</button>
        </div>
    </div>
</div>

@if($canEdit)
<!-- ================= EDIT BATCH EXPIRATION MODAL ================= -->
<div id="editBatchModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Edit Batch Expiration</h3>
        <p class="modal-subtitle" id="editBatchMedicineLabel"></p>

        <form id="editBatchForm" action="" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label>Batch/Lot Number</label>
                <input type="text" id="edit_batch_no" readonly>
            </div>

            <div class="form-group">
                <label>Current Quantity</label>
                <input type="text" id="edit_batch_quantity" readonly>
            </div>

            <div class="form-group">
                <label>Expiration Date</label>
                <input type="date" name="expiration_date" id="edit_batch_expiration_date" required>
                <p class="modal-hint">Only the expiration date will be changed. The batch number and quantity will remain unchanged.</p>
            </div>

            <div class="modal-actions">
                <button type="button" onclick="closeEditBatchModal()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-save">Update Expiration</button>
            </div>
        </form>
    </div>
</div>
@endif

@if($canAdd)
<!-- ================= ADD MODAL ================= -->
<div id="medicineModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Add Medicine</h3>
        <form action="{{ route($routePrefix . '.medicines.store') }}" method="POST">
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
                <label>Batch/Lot Number (optional)</label>
                <input type="text" name="batch_no" placeholder="Leave blank to auto-generate">
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
@endif

@if($canEdit)
<!-- ================= EDIT MODAL (descriptive fields only — never quantity) ================= -->
<div id="editMedicineModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Edit Medicine</h3>
        <p class="modal-subtitle">To change stock quantity, use Stock In, Stock Out, or Adjust Stock instead.</p>
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
            <p class="modal-subtitle">Batch expiration dates are managed per batch via Stock In / View Batches.</p>
            <div class="modal-actions">
                <button type="button" onclick="closeEditModal()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-save">Update</button>
            </div>
        </form>
    </div>
</div>
@endif

@if($canStockIn)
<!-- ================= STOCK IN MODAL ================= -->
<div id="stockInModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Stock In</h3>
        <p class="modal-subtitle" id="stockInMedicineLabel"></p>
        <form id="stockInForm" action="" method="POST">
            @csrf
            <div class="form-group">
                <label>Batch/Lot Number</label>
                <input type="text" name="batch_no" placeholder="e.g. LOT-002 (matches an existing batch, or creates a new one)" required>
            </div>
            <div class="form-group">
                <label>Expiration Date</label>
                <p class="modal-hint">Only used when this batch/lot number is new. Adding to an existing batch keeps its original expiration date.</p>
                <input type="date" name="expiration_date" required>
            </div>
            <div class="form-group">
                <label>Quantity to Add</label>
                <input type="number" name="quantity" min="1" step="1" required>
            </div>
            <div class="form-group">
                <label>Reason</label>
                <input type="text" name="reason" placeholder="e.g. New delivery" required>
            </div>
            <div class="form-group">
                <label>Reference Number (optional)</label>
                <input type="text" name="reference_no">
            </div>
            <div class="form-group">
                <label>Notes (optional)</label>
                <textarea name="notes" rows="2"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeStockModal('stockIn')" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-save">Confirm Stock In</button>
            </div>
        </form>
    </div>
</div>
@endif

@if($canStockOut)
<!-- ================= STOCK OUT MODAL ================= -->
<div id="stockOutModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Stock Out</h3>
        <p class="modal-subtitle" id="stockOutMedicineLabel"></p>
        <form id="stockOutForm" action="" method="POST">
            @csrf
            <div class="form-group">
                <label>Batch/Lot No.</label>
                <select name="batch_id" id="stockOutBatchSelect" onchange="updateBatchAvailable('stockOut')" required>
                    <option value="">Loading batches…</option>
                </select>
            </div>
            <div class="form-group">
                <label>Available Stock in Selected Batch</label>
                <p class="modal-hint" id="stockOutAvailable">—</p>
            </div>
            <div class="form-group">
                <label>Quantity to Remove</label>
                <input type="number" name="quantity" min="1" step="1" required>
            </div>
            <div class="form-group">
                <label>Reason</label>
                <input type="text" name="reason" placeholder="e.g. Medicine release" required>
            </div>
            <div class="form-group">
                <label>Reference Number (optional)</label>
                <input type="text" name="reference_no">
            </div>
            <div class="form-group">
                <label>Notes (optional)</label>
                <textarea name="notes" rows="2"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeStockModal('stockOut')" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-save">Confirm Stock Out</button>
            </div>
        </form>
    </div>
</div>
@endif

@if($canAdjustStock)
<!-- ================= ADJUST STOCK MODAL ================= -->
<div id="adjustStockModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Adjust Stock</h3>
        <p class="modal-subtitle" id="adjustStockMedicineLabel"></p>
        <form id="adjustStockForm" action="" method="POST">
            @csrf
            <div class="form-group">
                <label>Batch/Lot No.</label>
                <select name="batch_id" id="adjustStockBatchSelect" onchange="updateBatchAvailable('adjustStock')" required>
                    <option value="">Loading batches…</option>
                </select>
            </div>
            <div class="form-group">
                <label>Current Batch Stock</label>
                <p class="modal-hint" id="adjustStockAvailable">—</p>
            </div>
            <div class="form-group">
                <label>Adjustment Type</label>
                <select name="adjustment_type" required>
                    <option value="increase">Increase</option>
                    <option value="decrease">Decrease</option>
                </select>
            </div>
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="quantity" min="1" step="1" required>
            </div>
            <div class="form-group">
                <label>Reason</label>
                <input type="text" name="reason" placeholder="e.g. Physical inventory count" required>
            </div>
            <div class="form-group">
                <label>Reference Number (optional)</label>
                <input type="text" name="reference_no">
            </div>
            <div class="form-group">
                <label>Notes (optional)</label>
                <textarea name="notes" rows="2"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeStockModal('adjustStock')" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-save">Confirm Adjustment</button>
            </div>
        </form>
    </div>
</div>
@endif

@if($canDelete)
<!-- ================= SHARED DELETE FORM (submitted via the kebab menu) ================= -->
<form id="deleteMedicineForm" action="" method="POST" style="display:none;">
    @csrf
    @method('DELETE')
</form>
@endif

@endsection

@section('scripts')
<script>
const ROUTE_PREFIX = "{{ $routePrefix }}";
let activeMedicineId   = null;
let activeMedicineData = null;

const ROWS_PER_PAGE = 10;
let currentPage  = 1;
let filteredRows = [];

function getAllRows() {
    return Array.from(document.querySelectorAll('#medicineTableBody tr[data-id]'));
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

    const noResults = document.getElementById('noResults');
    noResults.style.display = filteredRows.length === 0 ? 'block' : 'none';

    renderPaginationInfo(start, pageRows.length);
    renderPaginationNav();
}

function renderPaginationInfo(start, count) {
    const info  = document.getElementById('paginationInfo');
    const total = filteredRows.length;

    if (total === 0) {
        info.innerHTML = 'No results found';
        return;
    }

    const from = start + 1;
    const to   = start + count;
    info.innerHTML = `Showing <strong>${from}–${to}</strong> of <strong>${total}</strong> result${total !== 1 ? 's' : ''}`;
}

function renderPaginationNav() {
    const nav        = document.getElementById('paginationNav');
    const totalPages = Math.ceil(filteredRows.length / ROWS_PER_PAGE);
    nav.innerHTML    = '';

    if (totalPages <= 1) return;

    /* ---- Previous ---- */
    const isPrevDisabled = currentPage === 1;
    const prev = document.createElement('span');
    prev.innerHTML  = '<i class="fa-solid fa-chevron-left"></i>';
    prev.className  = 'page-btn' + (isPrevDisabled ? ' disabled' : '');
    if (!isPrevDisabled) {
        prev.style.cursor = 'pointer';
        prev.onclick = () => { currentPage--; renderPage(); };
    }
    nav.appendChild(prev);

    /* ---- Page numbers with ellipsis ---- */
    const pages = [];
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || Math.abs(i - currentPage) <= 2) {
            pages.push(i);
        }
    }

    let prevPage = null;
    pages.forEach(page => {
        if (prevPage !== null && page - prevPage > 1) {
            const ellipsis = document.createElement('span');
            ellipsis.className   = 'page-ellipsis';
            ellipsis.textContent = '…';
            nav.appendChild(ellipsis);
        }

        const btn = document.createElement('span');
        btn.textContent = page;
        btn.className   = 'page-btn' + (page === currentPage ? ' active' : '');
        if (page !== currentPage) {
            btn.style.cursor = 'pointer';
            btn.onclick = () => { currentPage = page; renderPage(); };
        }
        nav.appendChild(btn);

        prevPage = page;
    });

    /* ---- Next ---- */
    const isNextDisabled = currentPage === totalPages;
    const next = document.createElement('span');
    next.innerHTML  = '<i class="fa-solid fa-chevron-right"></i>';
    next.className  = 'page-btn' + (isNextDisabled ? ' disabled' : '');
    if (!isNextDisabled) {
        next.style.cursor = 'pointer';
        next.onclick = () => { currentPage++; renderPage(); };
    }
    nav.appendChild(next);
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

/* ===== Export CSV ===== */
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

/* ===== Add Modal ===== */
function openModal()  { const m = document.getElementById('medicineModal'); if (m) m.style.display = 'flex'; }
function closeModal() { const m = document.getElementById('medicineModal'); if (m) m.style.display = 'none'; }

/* ===== Edit Modal (populated from the row's data attributes — no inline-JS escaping needed) ===== */
function openEditModalFromActive() {
    const d = activeMedicineData;
    if (!d) return;

    const form = document.getElementById('editMedicineForm');
    if (!form) return;

    form.action = `/${ROUTE_PREFIX}/medicines/${d.id}`;
    document.getElementById('edit_medicine_name').value   = d.medicineName;
    document.getElementById('edit_brand').value           = d.brandName;
    document.getElementById('edit_category').value        = d.category;
    document.getElementById('edit_dosage').value           = d.dosage;
    document.getElementById('edit_unit').value             = d.unit;
    document.getElementById('edit_price').value            = d.price;
    document.getElementById('editMedicineModal').style.display = 'flex';
}
function closeEditModal() { const m = document.getElementById('editMedicineModal'); if (m) m.style.display = 'none'; }

/* ===== View Details Modal (always fetched fresh from the server) ===== */
async function viewMedicine(btn) {
    const row = btn.closest('tr');
    const id  = row.dataset.id;
    const modal = document.getElementById('viewMedicineModal');
    const body  = document.getElementById('viewMedicineBody');

    body.innerHTML = '<div class="view-details-loading">Loading…</div>';
    modal.style.display = 'flex';

    try {
        const res = await fetch(`/${ROUTE_PREFIX}/medicines/${id}`, {
            headers: { 'Accept': 'application/json' },
        });

        if (!res.ok) {
            body.innerHTML = '<div class="view-details-loading">Unable to load medicine details.</div>';
            return;
        }

        const m = await res.json();

        const statusClass = m.status === 'Available' ? 'available'
            : (m.status === 'Low Stock' ? 'low-stock' : 'out-of-stock');

        body.innerHTML = `
            <div class="view-row"><span class="view-label">Medicine Name</span><span class="view-value">${escapeHtml(m.medicine_name)}</span></div>
            <div class="view-row"><span class="view-label">Brand</span><span class="view-value">${escapeHtml(m.brand)}</span></div>
            <div class="view-row"><span class="view-label">Category</span><span class="view-value">${escapeHtml(m.category)}</span></div>
            <div class="view-row"><span class="view-label">Dosage</span><span class="view-value">${escapeHtml(m.dosage)}</span></div>
            <div class="view-row"><span class="view-label">Current Stock</span><span class="view-value">${m.quantity} ${escapeHtml(m.unit)}</span></div>
            <div class="view-row"><span class="view-label">Price</span><span class="view-value">₱${m.price}</span></div>
            <div class="view-row"><span class="view-label">Expiration Date</span><span class="view-value">${escapeHtml(m.expiration_date ?? '—')}</span></div>
            <div class="view-row"><span class="view-label">Status</span><span class="view-value"><span class="badge ${statusClass}">${escapeHtml(m.status)}</span></span></div>
        `;
    } catch (e) {
        body.innerHTML = '<div class="view-details-loading">Unable to load medicine details.</div>';
    }
}
function closeViewModal() { const m = document.getElementById('viewMedicineModal'); if (m) m.style.display = 'none'; }

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/* ===== Stock In / Stock Out / Adjust Stock modals ===== */
const STOCK_MODAL_CONFIG = {
    stockIn:     { modalId: 'stockInModal',     formId: 'stockInForm',     labelId: 'stockInMedicineLabel',     endpoint: 'stock-in' },
    stockOut:    { modalId: 'stockOutModal',    formId: 'stockOutForm',    labelId: 'stockOutMedicineLabel',    endpoint: 'stock-out' },
    adjustStock: { modalId: 'adjustStockModal', formId: 'adjustStockForm', labelId: 'adjustStockMedicineLabel', endpoint: 'adjust-stock' },
};

function openStockModalByKey(key) {
    const cfg = STOCK_MODAL_CONFIG[key];
    const d   = activeMedicineData;
    if (!cfg || !d) return;

    const modal = document.getElementById(cfg.modalId);
    const form  = document.getElementById(cfg.formId);
    const label = document.getElementById(cfg.labelId);
    if (!modal || !form) return;

    form.reset();
    form.action = `/${ROUTE_PREFIX}/medicines/${d.id}/${cfg.endpoint}`;
    if (label) {
        label.textContent = `${d.medicineName} — Current Stock: ${d.quantity} ${d.unit}`;
    }
    modal.style.display = 'flex';

    if (key === 'stockOut') {
        populateBatchSelect(d.id, 'stockOutBatchSelect', { excludeExpired: true });
        document.getElementById('stockOutAvailable').textContent = '—';
    } else if (key === 'adjustStock') {
        populateBatchSelect(d.id, 'adjustStockBatchSelect', { excludeExpired: false });
        document.getElementById('adjustStockAvailable').textContent = '—';
    }
}

/* ===== Batch dropdowns for Stock Out / Adjust Stock (FEFO order: soonest-expiring first) ===== */
const batchCache = {}; // keyed by select element id -> array of batches for the active medicine

async function populateBatchSelect(medicineId, selectId, { excludeExpired }) {
    const select = document.getElementById(selectId);
    if (!select) return;

    select.innerHTML = '<option value="">Loading batches…</option>';

    try {
        const res = await fetch(`/${ROUTE_PREFIX}/medicines/${medicineId}/batches`, {
            headers: { 'Accept': 'application/json' },
        });
        if (!res.ok) throw new Error('Failed to load batches');

        const data = await res.json();
        let batches = data.batches.filter(b => b.is_active);
        if (excludeExpired) {
            batches = batches.filter(b => b.batch_status !== 'Expired');
        }

        batchCache[selectId] = batches;

        if (batches.length === 0) {
            select.innerHTML = '<option value="">No usable batches</option>';
            return;
        }

        select.innerHTML = batches.map(b =>
            `<option value="${escapeHtml(String(b.id))}" data-quantity="${b.quantity}">${escapeHtml(b.batch_no)} — ${escapeHtml(b.expiration_date)} (${b.quantity} available)</option>`
        ).join('');

        select.dispatchEvent(new Event('change'));
    } catch (e) {
        select.innerHTML = '<option value="">Unable to load batches</option>';
    }
}

function updateBatchAvailable(key) {
    const cfg = { stockOut: ['stockOutBatchSelect', 'stockOutAvailable'], adjustStock: ['adjustStockBatchSelect', 'adjustStockAvailable'] }[key];
    if (!cfg) return;
    const select = document.getElementById(cfg[0]);
    const label  = document.getElementById(cfg[1]);
    if (!select || !label) return;

    const option = select.options[select.selectedIndex];
    label.textContent = option && option.dataset.quantity !== undefined
        ? `${option.dataset.quantity} available`
        : '—';
}

/* ===== Batch View Modal ===== */
async function viewBatches(medicineId) {
    // Keep the selected medicine available even when the user opens
    // the batch list directly from the "N Batches ›" link instead
    // of the kebab menu. This lets Edit Batch build the correct URL.
    activeMedicineId = String(medicineId);

    const modal = document.getElementById('batchViewModal');
    const body  = document.getElementById('batchViewBody');
    const title = document.getElementById('batchViewTitle');

    body.innerHTML = '<tr><td colspan="{{ $canEdit ? 5 : 4 }}" class="no-data">Loading…</td></tr>';
    modal.style.display = 'flex';

    try {
        const res = await fetch(`/${ROUTE_PREFIX}/medicines/${medicineId}/batches`, {
            headers: { 'Accept': 'application/json' },
        });
        if (!res.ok) throw new Error('Failed to load batches');

        const data = await res.json();
        title.textContent = `Batches — ${data.medicine_name}`;

        if (!data.batches.length) {
            body.innerHTML = '<tr><td colspan="{{ $canEdit ? 5 : 4 }}" class="no-data">No batches recorded yet.</td></tr>';
            return;
        }

        const statusClass = s => s === 'Expired' ? 'out-of-stock' : (s === 'Expiring Soon' ? 'low-stock' : 'available');
                body.innerHTML = data.batches.map(b => `
            <tr>
                <td>${escapeHtml(b.batch_no)}</td>
                <td>${escapeHtml(b.expiration_date ?? '—')}</td>
                <td>${b.quantity}</td>
                <td><span class="badge ${statusClass(b.batch_status)}">${escapeHtml(b.batch_status)}</span></td>
                @if($canEdit)
                    <td>
                        <button type="button" class="btn-view batch-edit-btn"
                            data-batch-id="${escapeHtml(String(b.id))}"
                            data-batch-no="${escapeHtml(b.batch_no)}"
                            data-quantity="${escapeHtml(String(b.quantity))}"
                            data-expiration-date="${escapeHtml(b.expiration_date_iso ?? '')}"
                            data-medicine-name="${escapeHtml(data.medicine_name)}">Edit</button>
                    </td>
                @endif
            </tr>
        `).join('');

        body.querySelectorAll('.batch-edit-btn').forEach(button => {
            button.addEventListener('click', () => openEditBatchModal(button));
        });
    } catch (e) {
        body.innerHTML = '<tr><td colspan="{{ $canEdit ? 5 : 4 }}" class="no-data">Unable to load batches.</td></tr>';
    }
}
function closeBatchViewModal() { const m = document.getElementById('batchViewModal'); if (m) m.style.display = 'none'; }

/* ===== Edit Batch Expiration Modal ===== */
function openEditBatchModal(button) {
    const modal = document.getElementById('editBatchModal');
    const form = document.getElementById('editBatchForm');
    if (!modal || !form || !activeMedicineId || !button) return;

    form.action = `/${ROUTE_PREFIX}/medicines/${activeMedicineId}/batches/${button.dataset.batchId}`;
    document.getElementById('editBatchMedicineLabel').textContent = button.dataset.medicineName || '';
    document.getElementById('edit_batch_no').value = button.dataset.batchNo || '';
    document.getElementById('edit_batch_quantity').value = button.dataset.quantity || '0';
    document.getElementById('edit_batch_expiration_date').value = button.dataset.expirationDate || '';
    modal.style.display = 'flex';
}

function closeEditBatchModal() {
    const modal = document.getElementById('editBatchModal');
    if (modal) modal.style.display = 'none';
}

// Kept as thin wrappers so the "in"/"out" naming used by the modal
// close buttons stays short and readable in the markup above.
function openStockModal(type) {
    openStockModalByKey(type === 'in' ? 'stockIn' : 'stockOut');
}
function openAdjustModal() {
    openStockModalByKey('adjustStock');
}
function closeStockModal(key) {
    const cfg = STOCK_MODAL_CONFIG[key];
    if (!cfg) return;
    const modal = document.getElementById(cfg.modalId);
    if (modal) modal.style.display = 'none';
}

/* ===== Delete (shared form, submitted from the kebab menu) ===== */
function confirmDelete() {
    const d = activeMedicineData;
    if (!d) return;

    const ok = confirm(
        `Delete "${d.medicineName}"? It will be removed from the active inventory list, but its stock history will be preserved.`
    );
    if (!ok) return;

    const form = document.getElementById('deleteMedicineForm');
    if (!form) return;
    form.action = `/${ROUTE_PREFIX}/medicines/${d.id}`;
    form.submit();
}

/* ===== Kebab menu (single floating instance, repositioned per row) ===== */
function toggleKebab(event, btn) {
    event.stopPropagation();

    const menu = document.getElementById('kebabMenu');
    if (!menu) return;

    const row = btn.closest('tr');
    const id  = row.dataset.id;

    const alreadyOpenForThisRow = menu.style.display === 'block' && menu.dataset.forId === id;

    closeKebab();

    if (alreadyOpenForThisRow) {
        return;
    }

    activeMedicineId   = id;
    activeMedicineData = { ...row.dataset };

    positionKebabMenu(btn, menu);
    menu.dataset.forId = id;
    menu.style.display = 'block';
    btn.setAttribute('aria-expanded', 'true');
}

function positionKebabMenu(btn, menu) {
    const rect = btn.getBoundingClientRect();
    menu.style.visibility = 'hidden';
    menu.style.display = 'block';
    const menuRect = menu.getBoundingClientRect();
    menu.style.display = 'none';
    menu.style.visibility = 'visible';

    let left = rect.right - menuRect.width;
    left = Math.max(8, Math.min(left, window.innerWidth - menuRect.width - 8));

    let top = rect.bottom + 4;
    if (top + menuRect.height > window.innerHeight - 8 && rect.top - menuRect.height - 4 > 0) {
        top = rect.top - menuRect.height - 4;
    }

    menu.style.top  = `${top}px`;
    menu.style.left = `${left}px`;
}

function closeKebab() {
    const menu = document.getElementById('kebabMenu');
    if (!menu) return;
    menu.style.display = 'none';
    menu.removeAttribute('data-for-id');
    document.querySelectorAll('.btn-kebab[aria-expanded="true"]')
        .forEach(b => b.setAttribute('aria-expanded', 'false'));
}

const kebabMenuEl = document.getElementById('kebabMenu');
if (kebabMenuEl) {
    kebabMenuEl.addEventListener('click', function (e) {
        const item = e.target.closest('.kebab-item');
        if (!item || !activeMedicineId) return;

        const action = item.dataset.action;
        closeKebab();

        switch (action) {
            case 'edit':          openEditModalFromActive(); break;
            case 'stock-in':      openStockModal('in');      break;
            case 'stock-out':     openStockModal('out');     break;
            case 'adjust-stock':  openAdjustModal();          break;
            case 'view-batches':  viewBatches(activeMedicineId); break;
            case 'stock-history': window.location.href = `/${ROUTE_PREFIX}/medicines/${activeMedicineId}/stock-history`; break;
            case 'delete':        confirmDelete();            break;
        }
    });
}

/* ===== Close kebab / modals on outside click, scroll, or Escape ===== */
window.addEventListener('click', function (e) {
    const menu = document.getElementById('kebabMenu');
    if (menu && menu.style.display === 'block' && !menu.contains(e.target) && !e.target.closest('.btn-kebab')) {
        closeKebab();
    }

    ['medicineModal', 'editMedicineModal', 'editBatchModal', 'viewMedicineModal', 'batchViewModal', 'stockInModal', 'stockOutModal', 'adjustStockModal'].forEach(id => {
        const modal = document.getElementById(id);
        if (modal && e.target === modal) modal.style.display = 'none';
    });
});

window.addEventListener('scroll', closeKebab, true);
window.addEventListener('resize', closeKebab);

document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    closeKebab();
    ['medicineModal', 'editMedicineModal', 'editBatchModal', 'viewMedicineModal', 'batchViewModal', 'stockInModal', 'stockOutModal', 'adjustStockModal'].forEach(id => {
        const modal = document.getElementById(id);
        if (modal) modal.style.display = 'none';
    });
});
</script>
@endsection
