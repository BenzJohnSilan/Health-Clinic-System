<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\UserLog;
use App\Services\Archive\ArchiveException;
use App\Services\Archive\ArchiveService;
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
        // Archived services are listed in Admin > Archive instead.
        $services = Service::notArchived()->orderBy('name')->get();

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
        // An archived service must be restored from Admin > Archive before it can be edited.
        abort_if($service->isArchived(), 404);

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

    /**
     * Used to hard-delete the service, which blanked the service on every
     * past invoice line (invoice_items.service_id is SET NULL). It now
     * ARCHIVES it: the service is deactivated and its history stays linked.
     * The Consultation and Medical Certificate services are refused.
     */
    public function destroy(Service $service, ArchiveService $archive)
    {
        try {
            $archive->archive('service', $service->id, auth()->user());
        } catch (ArchiveException $e) {
            return redirect()->route('admin.services.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.services.index')
            ->with('success', '"' . $service->name . '" was moved to the Archive.');
    }
}
