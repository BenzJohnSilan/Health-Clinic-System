@extends('layouts.doctor')

@section('content')

<div class="container">

    <!-- HEADER -->
    <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h2>Medicine Management</h2>

        <button class="btn-add" onclick="openModal()">
            + Add Medicine
        </button>
    </div>

    <!-- SUCCESS MESSAGE -->
    @if(session('success'))
        <div class="alert-success" style="margin:10px 0; padding:10px; background:#d1fae5; color:#065f46;">
            {{ session('success') }}
        </div>
    @endif

    <!-- TABLE -->
    <table class="table" style="width:100%; margin-top:20px; border-collapse:collapse;">
        <thead>
            <tr style="background:#f3f4f6;">
                <th style="padding:10px; border:1px solid #ddd;">Medicine Name</th>
                <th style="padding:10px; border:1px solid #ddd;">Dosage</th>
                <th style="padding:10px; border:1px solid #ddd;">Instruction</th>
                <th style="padding:10px; border:1px solid #ddd;">Action</th>
            </tr>
        </thead>

        <tbody>
            @forelse($medicines ?? [] as $medicine)
                <tr>
                    <td style="padding:10px; border:1px solid #ddd;">
                        {{ $medicine->medicine_name }}
                    </td>

                    <td style="padding:10px; border:1px solid #ddd;">
                        {{ $medicine->dosage }}
                    </td>

                    <td style="padding:10px; border:1px solid #ddd;">
                        {{ $medicine->frequency }} - {{ $medicine->duration }}
                    </td>

                    <td style="padding:10px; border:1px solid #ddd;">
                        <form method="POST" action="{{ route('doctor.medicine.delete', $medicine->id) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="background:red; color:#fff; border:none; padding:5px 10px; cursor:pointer;">
                                Delete
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="padding:15px; text-align:center;">
                        No medicines found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

</div>

<!-- ================= MODAL ================= -->
<div id="medicineModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center;">

    <div style="background:#fff; padding:20px; width:350px; border-radius:10px;">

        <h3>Add Medicine</h3>

        <form method="POST" action="{{ route('doctor.medicine.store') }}">
            @csrf

            <input type="hidden" name="appointment_id" value="{{ $appointment->id ?? '' }}">

            <div style="margin-bottom:10px;">
                <label>Medicine Name</label>
                <input type="text" name="medicine_name" required style="width:100%; padding:8px;">
            </div>

            <div style="margin-bottom:10px;">
                <label>Dosage</label>
                <input type="text" name="dosage" placeholder="e.g. 500mg" required style="width:100%; padding:8px;">
            </div>

            <div style="margin-bottom:10px;">
                <label>Instruction (Frequency)</label>
                <input type="text" name="frequency" placeholder="e.g. 2x a day" required style="width:100%; padding:8px;">
            </div>

            <div style="margin-bottom:10px;">
                <label>Duration</label>
                <input type="text" name="duration" placeholder="e.g. 5 days" required style="width:100%; padding:8px;">
            </div>

            <div style="display:flex; justify-content:space-between; margin-top:15px;">
                <button type="button" onclick="closeModal()" style="padding:8px 12px;">
                    Cancel
                </button>

                <button type="submit" style="background:green; color:#fff; padding:8px 12px; border:none;">
                    Save
                </button>
            </div>

        </form>

    </div>
</div>

<!-- ================= SCRIPT ================= -->
<script>
function openModal(){
    document.getElementById('medicineModal').style.display = 'flex';
}

function closeModal(){
    document.getElementById('medicineModal').style.display = 'none';
}
</script>

@endsection