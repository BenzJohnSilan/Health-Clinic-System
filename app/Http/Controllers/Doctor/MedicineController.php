<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use Illuminate\Http\Request;

class MedicineController extends Controller
{
    // Show inventory list
    public function index()
    {
        $medicines = Medicine::all();
        return view('doctor.medicines.index', compact('medicines'));
    }

    // Store new medicine in inventory
    public function store(Request $request)
    {
        $request->validate([
            'medicine_name'   => 'required|string',
            'category'        => 'required|string',
            'quantity'        => 'required|integer|min:1',
            'unit'            => 'required|string',
            'expiration_date' => 'required|date',
        ]);

        Medicine::create([
            'medicine_name'   => $request->medicine_name,
            'category'        => $request->category,
            'quantity'        => $request->quantity,
            'unit'            => $request->unit,
            'expiration_date' => $request->expiration_date,
            'status'          => 'Available',
        ]);

        return redirect()->route('doctor.medicines.index')
                         ->with('success', 'Medicine added successfully!');
    }

    // Delete medicine from inventory
    public function destroy(Medicine $medicine)
    {
        $medicine->delete();
        return redirect()->route('doctor.medicines.index')
                         ->with('success', 'Medicine deleted!');
    }
}