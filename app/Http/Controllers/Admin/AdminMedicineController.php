<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Models\UserLog;
use Illuminate\Http\Request;

class AdminMedicineController extends Controller
{
    // ===============================
    // Compute stock status
    // ===============================
    private function computeStatus(int $quantity): string
    {
        return match (true) {
            $quantity <= 0  => 'Out of Stock',
            $quantity <= 10 => 'Low Stock',
            default         => 'Available',
        };
    }

    // ===============================
    // Show all medicines
    // ===============================
    public function index()
    {
        $medicines = Medicine::all();
        return view('admin.medicines.index', compact('medicines'));
    }

    // ===============================
    // Store new medicine
    // ===============================
    public function store(Request $request)
    {
        $request->validate([
            'medicine_name'   => 'required|string',
            'brand'           => 'required|string',
            'category'        => 'required|string',
            'dosage'          => 'required|string',
            'quantity'        => 'required|integer|min:0',
            'unit'            => 'required|string',
            'price'           => 'required|numeric|min:0',
            'expiration_date' => 'required|date',
        ]);

        $medicine = Medicine::create([
            'medicine_name'   => $request->medicine_name,
            'brand'           => $request->brand,
            'category'        => $request->category,
            'dosage'          => $request->dosage,
            'quantity'        => $request->quantity,
            'unit'            => $request->unit,
            'price'           => $request->price,
            'expiration_date' => $request->expiration_date,
            'status'          => $this->computeStatus($request->quantity),
        ]);

        // ================= LOG =================
        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Added Medicine',
            'details' => $medicine->medicine_name
        ]);

        return redirect()->route('admin.medicines.index')
                         ->with('success', 'Medicine added successfully!');
    }

    // ===============================
    // UPDATE medicine
    // ===============================
    public function update(Request $request, $id)
    {
        $request->validate([
            'medicine_name'   => 'required|string',
            'brand'           => 'required|string',
            'category'        => 'required|string',
            'dosage'          => 'required|string',
            'quantity'        => 'required|integer|min:0',
            'unit'            => 'required|string',
            'price'           => 'required|numeric|min:0',
            'expiration_date' => 'required|date',
        ]);

        $medicine = Medicine::findOrFail($id);

        $medicine->update([
            'medicine_name'   => $request->medicine_name,
            'brand'           => $request->brand,
            'category'        => $request->category,
            'dosage'          => $request->dosage,
            'quantity'        => $request->quantity,
            'unit'            => $request->unit,
            'price'           => $request->price,
            'expiration_date' => $request->expiration_date,
            'status'          => $this->computeStatus($request->quantity),
        ]);

        // ================= LOG =================
        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Updated Medicine',
            'details' => $medicine->medicine_name
        ]);

        return redirect()->route('admin.medicines.index')
                         ->with('success', 'Medicine updated successfully!');
    }

    // ===============================
    // DELETE medicine
    // ===============================
    public function destroy(Medicine $medicine)
    {
        // ================= LOG =================
        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Deleted Medicine',
            'details' => $medicine->medicine_name
        ]);

        $medicine->delete();

        return redirect()->route('admin.medicines.index')
                         ->with('success', 'Medicine deleted successfully!');
    }
}