<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Bookings\Actions\ConfirmBookingAction;
use App\Domain\Bookings\Actions\CreateBookingAction;
use App\Domain\Bookings\Models\Booking;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $bookings = Booking::query()
            ->with(['customer:id,name,mobile', 'vehicle:id,stock_number,make,model'])
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), ['branch' => 'branch_id', 'assigned' => 'sales_executive_id', 'owner' => 'created_by']))
            ->when($request->query('filter.status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return ApiResponse::paginated($bookings->through(fn (Booking $b) => $this->row($b)));
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        $booking->load(['customer:id,name,mobile', 'vehicle:id,stock_number,make,model', 'payments']);

        return ApiResponse::success([
            ...$this->row($booking),
            'discount_amount' => $booking->discount_amount,
            'booking_amount' => $booking->booking_amount,
            'net_payable' => $booking->netPayable(),
            'paid_amount' => $booking->paidAmount(),
            'payment_mode' => $booking->payment_mode,
            'allowed_transitions' => array_map(fn ($s) => $s->value, $booking->status->allowedTransitions()),
        ]);
    }

    public function store(Request $request, CreateBookingAction $action): JsonResponse
    {
        $this->authorize('create', Booking::class);

        $data = $request->validate([
            'sales_lead_id' => ['required', 'integer', 'exists:sales_leads,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'booking_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_mode' => ['required', 'string', 'in:cash,finance'],
        ]);

        $lead = SalesLead::query()->findOrFail($data['sales_lead_id']);
        $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
        $booking = $action->execute($lead, $vehicle, $data, $request->user());

        return ApiResponse::success($this->row($booking), 'Booking created.', status: 201);
    }

    public function confirm(Request $request, Booking $booking, ConfirmBookingAction $action): JsonResponse
    {
        $this->authorize('update', $booking);

        $action->execute($booking, $request->user());

        return ApiResponse::success(['status' => $booking->fresh()->status->value], 'Booking processed.');
    }

    private function row(Booking $b): array
    {
        return [
            'id' => $b->id,
            'booking_number' => $b->booking_number,
            'customer' => $b->customer?->only(['id', 'name', 'mobile']),
            'vehicle' => $b->vehicle?->only(['id', 'stock_number', 'make', 'model']),
            'selling_price' => $b->selling_price,
            'status' => $b->status->value,
        ];
    }
}
