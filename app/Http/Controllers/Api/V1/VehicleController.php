<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Documents\Services\MediaUploadService;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Inventory\Services\VehicleExpenseService;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Vehicle::class);

        $canCost = $request->user()->can('vehicles.view-purchase-cost');

        $vehicles = Vehicle::query()
            ->with('branch:id,name')
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), ['branch' => 'branch_id', 'owner' => 'created_by']))
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('stock_number', 'like', "%{$s}%")
                ->orWhere('registration_number', 'like', "%{$s}%")
                ->orWhere('make', 'like', "%{$s}%")
                ->orWhere('model', 'like', "%{$s}%")))
            ->when($request->query('filter.status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return ApiResponse::paginated($vehicles->through(fn (Vehicle $v) => [
            'id' => $v->id,
            'stock_number' => $v->stock_number,
            'title' => trim($v->make.' '.$v->model.' '.($v->variant ?? '')),
            'registration_number' => $v->registration_number,
            'manufacturing_year' => $v->manufacturing_year,
            'fuel_type' => $v->fuel_type,
            'transmission' => $v->transmission,
            'odometer_km' => $v->odometer_km,
            'status' => $v->status->value,
            'asking_price' => $v->asking_price,
            'landed_cost' => $canCost ? $v->landed_cost : null,
            'branch' => $v->branch?->only(['id', 'name']),
            'published_web' => $v->published_web,
        ]));
    }

    public function show(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        $canCost = $request->user()->can('vehicles.view-purchase-cost');
        $vehicle->load(['branch:id,name', 'media', 'documents']);

        return ApiResponse::success([
            'id' => $vehicle->id,
            'stock_number' => $vehicle->stock_number,
            'make' => $vehicle->make,
            'model' => $vehicle->model,
            'variant' => $vehicle->variant,
            'registration_number' => $vehicle->registration_number,
            'manufacturing_year' => $vehicle->manufacturing_year,
            'fuel_type' => $vehicle->fuel_type,
            'transmission' => $vehicle->transmission,
            'color' => $vehicle->color,
            'odometer_km' => $vehicle->odometer_km,
            'status' => $vehicle->status->value,
            'asking_price' => $vehicle->asking_price,
            'landed_cost' => $canCost ? $vehicle->landed_cost : null,
            'parking_location' => $vehicle->parking_location,
            'inspection_grade' => $vehicle->inspection_grade,
            'media' => $vehicle->media->map(fn ($m) => [
                'id' => $m->id, 'type' => $m->type, 'category' => $m->category,
                'url' => app(MediaUploadService::class)->signedUrl($m->file_path),
            ]),
            'documents' => $vehicle->documents->map(fn ($d) => [
                'id' => $d->id, 'type' => $d->type, 'number' => $d->number, 'status' => $d->status,
            ]),
        ]);
    }

    public function uploadMedia(Request $request, Vehicle $vehicle, MediaUploadService $media): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,mp4,mov'],
            'category' => ['nullable', 'string', 'max:40'],
        ]);

        $stored = $media->store($data['file'], "vehicles/{$vehicle->id}");
        $isVideo = str_starts_with($data['file']->getClientMimeType(), 'video/');

        $row = $vehicle->media()->create([
            'type' => $isVideo ? 'video' : 'photo',
            'category' => $data['category'] ?? null,
            'file_path' => $stored['path'],
            'thumbnail_path' => $stored['thumbnail_path'],
            'is_primary' => $vehicle->media()->count() === 0,
            'sort_order' => $vehicle->media()->count(),
            'uploaded_by' => $request->user()->id,
        ]);

        return ApiResponse::success(['id' => $row->id], 'Media uploaded.', status: 201);
    }

    public function addExpense(Request $request, Vehicle $vehicle, VehicleExpenseService $service): JsonResponse
    {
        abort_unless($request->user()->can('refurbishment.create') || $request->user()->can('vehicles.update'), 403);

        $data = $request->validate([
            'category' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
        ]);

        $expense = $service->create($vehicle, $data, $request->user());

        return ApiResponse::success(['id' => $expense->id, 'expense_number' => $expense->expense_number], 'Expense recorded.', status: 201);
    }
}
