<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Branches\Models\Branch;
use App\Domain\Vendors\Models\Vendor;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Vendor::class);

        $vendors = Vendor::query()
            ->with('branch:id,name')
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('code', 'like', "%{$s}%")
                ->orWhere('phone', 'like', "%{$s}%")))
            ->when($request->string('type')->toString(), fn ($q, $t) => $q->where('type', $t))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/vendors/Index', [
            'vendors' => $vendors,
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'type' => $request->string('type')->toString() ?: null,
            ],
            'can' => [
                'create' => $request->user()->can('create', Vendor::class),
                'update' => $request->user()->can('vendors.update'),
                'delete' => $request->user()->can('vendors.delete'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Vendor::class);

        $vendor = Vendor::query()->create($this->validated($request));

        return back()->with('success', "Vendor {$vendor->name} created.");
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorize('update', $vendor);

        $vendor->update($this->validated($request, $vendor));

        return back()->with('success', 'Vendor updated.');
    }

    public function destroy(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorize('delete', $vendor);

        $vendor->delete();

        return back()->with('success', 'Vendor removed.');
    }

    private function validated(Request $request, ?Vendor $vendor = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:workshop,parts,rto_agent,transport,other'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'gst_number' => ['nullable', 'string', 'max:20'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->withoutTrashed()],
            'is_active' => ['boolean'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $data['code'] = $vendor?->code ?? 'VEN-'.strtoupper(Str::random(6));

        return $data;
    }
}
