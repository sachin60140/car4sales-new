<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Bookings\Models\Booking;
use App\Domain\Deliveries\Actions\DeliveryAction;
use App\Domain\Deliveries\Models\Delivery;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Delivery::class);

        $deliveries = Delivery::query()
            ->with(['customer:id,name,mobile', 'vehicle:id,stock_number,make,model'])
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), ['branch' => 'branch_id', 'owner' => 'created_by']))
            ->when($request->query('filter.status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return ApiResponse::paginated($deliveries->through(fn (Delivery $d) => $this->row($d)));
    }

    public function store(Request $request, DeliveryAction $action): JsonResponse
    {
        $this->authorize('create', Delivery::class);

        $data = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
        ]);

        $booking = Booking::query()->findOrFail($data['booking_id']);
        $delivery = $action->create($booking, $request->user());

        return ApiResponse::success($this->detail($delivery->fresh()), 'Delivery opened.', status: 201);
    }

    public function show(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorize('view', $delivery);

        $delivery->load(['customer:id,name,mobile', 'vehicle:id,stock_number,make,model,registration_number']);

        return ApiResponse::success($this->detail($delivery));
    }

    public function setChecks(Request $request, Delivery $delivery, DeliveryAction $action): JsonResponse
    {
        $this->authorize('update', $delivery);

        $manual = ['chk_quality_check', 'chk_insurance', 'chk_rto_papers_signed', 'chk_accessories', 'chk_cleaned', 'chk_documents_prepared'];
        $rules = [];
        foreach ($manual as $field) {
            $rules[$field] = ['sometimes', 'boolean'];
        }
        $data = $request->validate($rules);

        $action->refreshChecklist($delivery);
        $delivery = $action->setManualChecks($delivery->fresh(), $data);

        return ApiResponse::success($this->detail($delivery), 'Checklist updated.');
    }

    public function approve(Request $request, Delivery $delivery, DeliveryAction $action): JsonResponse
    {
        $this->authorize('approve', $delivery);

        $delivery = $action->approve($delivery, $request->user());

        return ApiResponse::success($this->detail($delivery), 'Delivery approved.');
    }

    public function complete(Request $request, Delivery $delivery, DeliveryAction $action): JsonResponse
    {
        $this->authorize('update', $delivery);

        $data = $request->validate([
            'odometer' => ['nullable', 'integer', 'min:0'],
            'fuel_level' => ['nullable', 'string', 'max:20'],
            'dc_keys' => ['sometimes', 'boolean'],
            'dc_spare_key' => ['sometimes', 'boolean'],
            'dc_rc_copy' => ['sometimes', 'boolean'],
            'dc_insurance' => ['sometimes', 'boolean'],
            'dc_invoice' => ['sometimes', 'boolean'],
            'dc_tool_kit' => ['sometimes', 'boolean'],
            'dc_spare_wheel' => ['sometimes', 'boolean'],
            'dc_accessories' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $delivery = $action->complete($delivery, $request->user(), $data);

        return ApiResponse::success($this->detail($delivery), 'Vehicle delivered. RTO case created.');
    }

    private function row(Delivery $d): array
    {
        return [
            'id' => $d->id,
            'delivery_number' => $d->delivery_number,
            'customer' => $d->customer?->only(['id', 'name', 'mobile']),
            'vehicle' => $d->vehicle?->only(['id', 'stock_number', 'make', 'model']),
            'status' => $d->status->value,
            'status_label' => $d->status->label(),
            'delivered_at' => $d->delivered_at?->toIso8601String(),
        ];
    }

    private function detail(Delivery $d): array
    {
        $checks = [];
        foreach (Delivery::APPROVAL_CHECKS as $check) {
            $checks[$check] = (bool) $d->{$check};
        }

        return [
            ...$this->row($d),
            'scheduled_at' => $d->scheduled_at?->toIso8601String(),
            'odometer' => $d->odometer,
            'fuel_level' => $d->fuel_level,
            'checklist' => $checks,
            'checklist_complete' => $d->approvalChecklistComplete(),
        ];
    }
}
