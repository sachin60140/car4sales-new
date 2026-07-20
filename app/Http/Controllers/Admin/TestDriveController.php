<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Inventory\Models\Vehicle;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Domain\TestDrives\Actions\TestDriveAction;
use App\Domain\TestDrives\Models\TestDrive;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TestDriveController extends Controller
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('test-drives.view'), 403);

        $drives = TestDrive::query()
            ->with(['lead:id,lead_number', 'customer:id,name,mobile', 'vehicle:id,stock_number,make,model', 'branch:id,name'])
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), ['branch' => 'branch_id', 'assigned' => 'accompanied_by', 'owner' => 'created_by']))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->latest('scheduled_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (TestDrive $t) => [
                'id' => $t->id,
                'td_number' => $t->td_number,
                'customer' => $t->customer?->only(['id', 'name', 'mobile']),
                'vehicle' => $t->vehicle?->only(['id', 'stock_number', 'make', 'model']),
                'lead' => $t->lead?->only(['id', 'lead_number']),
                'scheduled_at' => $t->scheduled_at?->toDateTimeString(),
                'status' => $t->status,
            ]);

        return Inertia::render('admin/test-drives/Index', [
            'drives' => $drives,
            'filters' => ['status' => $request->string('status')->toString() ?: null],
        ]);
    }

    public function store(Request $request, SalesLead $salesLead, TestDriveAction $action): RedirectResponse
    {
        abort_unless($request->user()->can('test-drives.create') && $request->user()->can('update', $salesLead), 403);

        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'scheduled_at' => ['required', 'date'],
            'driving_licence_number' => ['nullable', 'string', 'max:255'],
            'accompanied_by' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
        $action->schedule($salesLead, $vehicle, $data, $request->user());

        return back()->with('success', 'Test drive scheduled.');
    }

    public function complete(Request $request, TestDrive $testDrive, TestDriveAction $action): RedirectResponse
    {
        abort_unless($request->user()->can('test-drives.update'), 403);

        $data = $request->validate([
            'start_odometer' => ['nullable', 'integer', 'min:0'],
            'end_odometer' => ['nullable', 'integer', 'min:0'],
            'fuel_level' => ['nullable', 'string', 'max:20'],
            'route' => ['nullable', 'string', 'max:255'],
            'damage_acknowledged' => ['boolean'],
            'feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $action->complete($testDrive, $data, $request->user());

        return back()->with('success', 'Test drive completed.');
    }
}
