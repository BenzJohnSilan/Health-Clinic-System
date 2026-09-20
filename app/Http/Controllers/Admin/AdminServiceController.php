<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\UserLog;
use Illuminate\Http\Request;

/**
 * Manage the clinic's Services / fee structure used by the Billing &
 * Payments module (Doctors pick from this list — prices are never
 * hardcoded in a controller).
 */
class AdminServiceController extends Controller
{
    public function index()
    {
        $services = Service::orderBy('name')->get();

        return view('admin.billing.services', compact('services'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price'       => 'required|numeric|min:0',
        ]);

        $service = Service::create([
            'name'        => $request->name,
            'description' => $request->description,
            'price'       => $request->price,
            'is_active'   => true,
        ]);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Added Service',
            'module'  => 'Services',
            'details' => $service->name,
        ]);

        return redirect()->route('admin.services.index')
            ->with('success', 'Service added successfully!');
    }

    public function update(Request $request, Service $service)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price'       => 'required|numeric|min:0',
            'is_active'   => 'nullable|boolean',
        ]);

        $service->update([
            'name'        => $request->name,
            'description' => $request->description,
            'price'       => $request->price,
            'is_active'   => $request->boolean('is_active'),
        ]);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Updated Service',
            'module'  => 'Services',
            'details' => $service->name,
        ]);

        return redirect()->route('admin.services.index')
            ->with('success', 'Service updated successfully!');
    }

    public function destroy(Service $service)
    {
        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Deleted Service',
            'module'  => 'Services',
            'details' => $service->name,
        ]);

        $service->delete();

        return redirect()->route('admin.services.index')
            ->with('success', 'Service deleted successfully!');
    }
}
