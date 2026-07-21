<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Branches\Models\Branch;
use App\Domain\Inventory\Actions\CreateStockAction;
use App\Domain\Inventory\Enums\ExpenseCategory;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Domain\Vendors\Models\Vendor;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Vehicle::class);

        $canCost = $request->user()->can('vehicles.view-purchase-cost');

        $vehicles = Vehicle::query()
            ->with('branch:id,name')
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), ['branch' => 'branch_id', 'owner' => 'created_by']))
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('stock_number', 'like', "%{$s}%")
                ->orWhere('registration_number', 'like', "%{$s}%")
                ->orWhere('make', 'like', "%{$s}%")
                ->orWhere('model', 'like', "%{$s}%")))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->integer('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->string('published')->toString(), fn ($q, $p) => $q->where('published_web', $p === 'yes'))
            ->when($request->boolean('refurb_required'), fn ($q) => $q->where('refurb_required', true))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Vehicle $v) => [
                'id' => $v->id,
                'stock_number' => $v->stock_number,
                'title' => trim($v->make.' '.$v->model.' '.($v->variant ?? '')),
                'registration_number' => $v->registration_number,
                'manufacturing_year' => $v->manufacturing_year,
                'branch' => $v->branch?->only(['id', 'name']),
                'status' => $v->status->value,
                'status_label' => $v->status->label(),
                'asking_price' => $v->asking_price,
                'landed_cost' => $canCost ? $v->landed_cost : null,
                'published_web' => $v->published_web,
                'age_days' => $v->ageDays(),
            ]);

        return Inertia::render('admin/inventory/Index', [
            'vehicles' => $vehicles,
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => array_map(fn (VehicleStatus $s) => ['value' => $s->value, 'label' => $s->label()], VehicleStatus::cases()),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString() ?: null,
                'branch_id' => $request->integer('branch_id') ?: null,
                'published' => $request->string('published')->toString() ?: null,
                'refurb_required' => $request->boolean('refurb_required'),
            ],
            'can' => [
                'viewCost' => $canCost,
                'create' => $request->user()->can('create', Vehicle::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Vehicle::class);

        return Inertia::render('admin/inventory/Create', [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statuses' => array_map(
                fn (VehicleStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                [VehicleStatus::InStock, VehicleStatus::InwardPending, VehicleStatus::InspectionPending, VehicleStatus::UnderRefurbishment],
            ),
            'canCost' => $request->user()->can('vehicles.view-purchase-cost'),
        ]);
    }

    public function store(Request $request, CreateStockAction $action): RedirectResponse
    {
        $this->authorize('create', Vehicle::class);

        $data = $request->validate([
            'make' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'variant' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:20'],
            'chassis_number' => ['nullable', 'string', 'max:40'],
            'engine_number' => ['nullable', 'string', 'max:40'],
            'manufacturing_year' => ['nullable', 'integer', 'min:1980', 'max:'.(now()->year + 1)],
            'registration_state' => ['nullable', 'string', 'max:100'],
            'fuel_type' => ['nullable', 'string', 'max:30'],
            'transmission' => ['nullable', 'string', 'max:30'],
            'body_type' => ['nullable', 'string', 'max:30'],
            'color' => ['nullable', 'string', 'max:40'],
            'odometer_km' => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'ownership_serial' => ['nullable', 'integer', 'min:1', 'max:20'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'status' => ['required', Rule::in([
                VehicleStatus::InStock->value, VehicleStatus::InwardPending->value,
                VehicleStatus::InspectionPending->value, VehicleStatus::UnderRefurbishment->value,
            ])],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'landed_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'asking_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'refurb_required' => ['boolean'],
        ]);

        // Only cost-privileged users may set purchase figures.
        if (! $request->user()->can('vehicles.view-purchase-cost')) {
            unset($data['purchase_price'], $data['landed_cost']);
        }

        $vehicle = $action->execute($data, $request->user());

        return redirect()
            ->route('admin.inventory.show', $vehicle)
            ->with('success', "Stock {$vehicle->stock_number} added.");
    }

    public function show(Request $request, Vehicle $vehicle): Response
    {
        $this->authorize('view', $vehicle);

        $user = $request->user();
        $canCost = $user->can('vehicles.view-purchase-cost');

        $vehicle->load([
            'branch:id,name',
            'media.uploader:id,name',
            'documents',
            'movements.fromBranch:id,name', 'movements.toBranch:id,name', 'movements.mover:id,name',
            'expenses.vendor:id,name', 'expenses.approver:id,name',
            'priceHistory.changer:id,name',
            'statusHistories.changer:id,name',
            'workshopJobs.vendor:id,name',
        ]);

        $data = $vehicle->toArray();
        if (! $canCost) {
            unset($data['purchase_price'], $data['landed_cost'], $data['minimum_selling_price']);
            $data['expenses'] = collect($data['expenses'])->map(function ($e) {
                return $e;
            });
        }

        return Inertia::render('admin/inventory/Show', [
            'vehicle' => $data,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']),
            'expenseCategories' => ExpenseCategory::options(),
            'movementTypes' => MovementType::options(),
            'statuses' => array_map(fn (VehicleStatus $s) => ['value' => $s->value, 'label' => $s->label()], VehicleStatus::cases()),
            'allowedTransitions' => array_map(
                fn (VehicleStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                $vehicle->status->allowedTransitions(),
            ),
            'can' => [
                'update' => $user->can('update', $vehicle),
                'viewCost' => $canCost,
                'viewProfit' => $user->can('vehicles.view-profit'),
                'manageExpenses' => $user->can('refurbishment.create') || $user->can('vehicles.update'),
                'approveExpenses' => $user->can('refurbishment.approve'),
                'reverseExpenses' => $user->can('payments.reverse-payment') || $user->hasRole('Accounts Manager'),
                'transfer' => $user->can('vehicles.update'),
                'publish' => $user->can('vehicles.update'),
                'workshop' => $user->can('refurbishment.create'),
            ],
        ]);
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('update', $vehicle);

        $data = $request->validate([
            'registration_number' => ['nullable', 'string', 'max:20'],
            'chassis_number' => ['nullable', 'string', 'max:40'],
            'engine_number' => ['nullable', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'max:40'],
            'body_type' => ['nullable', 'string', 'max:30'],
            'registration_state' => ['nullable', 'string', 'max:100'],
            'ownership_serial' => ['nullable', 'integer', 'min:1', 'max:20'],
            'insurance_status' => ['nullable', 'string', 'max:30'],
            'insurance_valid_till' => ['nullable', 'date'],
            'parking_location' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'refurb_required' => ['boolean'],
        ]);

        $vehicle->update($data);

        return back()->with('success', 'Vehicle updated.');
    }
}
