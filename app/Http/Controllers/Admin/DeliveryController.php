<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Models\Booking;
use App\Domain\Deliveries\Actions\DeliveryAction;
use App\Domain\Deliveries\Enums\DeliveryStatus;
use App\Domain\Deliveries\Models\Delivery;
use App\Domain\Documents\Services\DocumentGenerator;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController extends Controller
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Delivery::class);

        $deliveries = Delivery::query()
            ->with(['customer:id,name,mobile', 'vehicle:id,stock_number,make,model', 'booking:id,booking_number', 'branch:id,name'])
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), ['branch' => 'branch_id', 'owner' => 'created_by']))
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('delivery_number', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('mobile', 'like', "%{$s}%"))
                ->orWhereHas('vehicle', fn ($v) => $v->where('stock_number', 'like', "%{$s}%"))))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Delivery $d) => [
                'id' => $d->id,
                'delivery_number' => $d->delivery_number,
                'customer' => $d->customer?->only(['id', 'name', 'mobile']),
                'vehicle' => $d->vehicle === null ? null : [
                    'id' => $d->vehicle->id,
                    'stock_number' => $d->vehicle->stock_number,
                    'title' => trim("{$d->vehicle->make} {$d->vehicle->model}"),
                ],
                'booking' => $d->booking?->only(['id', 'booking_number']),
                'branch' => $d->branch?->only(['id', 'name']),
                'status' => $d->status->value,
                'status_label' => $d->status->label(),
                'scheduled_at' => $d->scheduled_at?->toDateTimeString(),
                'delivered_at' => $d->delivered_at?->toDateTimeString(),
            ]);

        // Bookings that are confirmed/ready but do not yet have an active delivery.
        $eligibleBookings = Booking::query()
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::PaymentPending->value, BookingStatus::FinancePending->value, BookingStatus::ReadyForDelivery->value])
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('deliveries')
                    ->whereColumn('deliveries.booking_id', 'bookings.id')
                    ->where('deliveries.status', '!=', DeliveryStatus::Cancelled->value)
                    ->whereNull('deliveries.deleted_at');
            })
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), ['branch' => 'branch_id', 'assigned' => 'sales_executive_id', 'owner' => 'created_by']))
            ->with(['customer:id,name', 'vehicle:id,stock_number,make,model'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Booking $b) => [
                'id' => $b->id,
                'booking_number' => $b->booking_number,
                'label' => "{$b->booking_number} — ".($b->customer?->name ?? 'Customer').' / '.trim("{$b->vehicle?->make} {$b->vehicle?->model}"),
            ]);

        return Inertia::render('admin/deliveries/Index', [
            'deliveries' => $deliveries,
            'statuses' => DeliveryStatus::options(),
            'eligibleBookings' => $eligibleBookings,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString() ?: null,
            ],
            'can' => [
                'create' => $request->user()->can('create', Delivery::class),
            ],
        ]);
    }

    public function store(Request $request, DeliveryAction $action): RedirectResponse
    {
        $this->authorize('create', Delivery::class);

        $data = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
        ]);

        $booking = Booking::query()->findOrFail($data['booking_id']);
        $delivery = $action->create($booking, $request->user());

        return redirect()->route('admin.deliveries.show', $delivery)
            ->with('success', "Delivery {$delivery->delivery_number} opened.");
    }

    public function show(Request $request, Delivery $delivery): Response
    {
        $this->authorize('view', $delivery);

        $delivery->load([
            'customer:id,name,mobile,email,kyc_status',
            'vehicle:id,stock_number,make,model,variant,registration_number,color',
            'booking:id,booking_number,selling_price,discount_amount,exchange_adjustment,payment_mode,status',
            'branch:id,name', 'approver:id,name', 'documents.uploader:id,name',
        ]);

        $rtoCase = \App\Domain\RTO\Models\RtoCase::query()
            ->where('delivery_id', $delivery->id)
            ->first(['id', 'rto_number', 'status']);

        return Inertia::render('admin/deliveries/Show', [
            'delivery' => [
                ...$delivery->only([
                    'id', 'delivery_number', 'status', 'scheduled_at', 'delivered_at', 'odometer',
                    'fuel_level', 'remarks', 'approved_at',
                    ...Delivery::APPROVAL_CHECKS,
                    'dc_keys', 'dc_spare_key', 'dc_rc_copy', 'dc_insurance', 'dc_invoice',
                    'dc_tool_kit', 'dc_spare_wheel', 'dc_accessories',
                    'customer_photo_path', 'delivery_photo_path', 'customer_signature_path', 'employee_signature_path',
                ]),
                'status_label' => $delivery->status->label(),
                'customer' => $delivery->customer,
                'vehicle' => $delivery->vehicle,
                'booking' => $delivery->booking,
                'branch' => $delivery->branch?->only(['id', 'name']),
                'approver' => $delivery->approver?->only(['id', 'name']),
                'documents' => $delivery->documents->map(fn ($doc) => [
                    'id' => $doc->id, 'type' => $doc->type, 'file_path' => $doc->file_path,
                    'handed_over' => $doc->handed_over, 'uploader' => $doc->uploader?->only(['id', 'name']),
                ]),
                'checklist_complete' => $delivery->approvalChecklistComplete(),
            ],
            'rtoCase' => $rtoCase,
            'checklistFields' => $this->checklistMeta(),
            'can' => [
                'update' => $request->user()->can('update', $delivery),
                'approve' => $request->user()->can('approve', $delivery),
                'print' => $request->user()->can('deliveries.print'),
            ],
        ]);
    }

    public function refreshChecklist(Request $request, Delivery $delivery, DeliveryAction $action): RedirectResponse
    {
        $this->authorize('update', $delivery);

        $action->refreshChecklist($delivery);

        return back()->with('success', 'Checklist refreshed from live records.');
    }

    public function setChecks(Request $request, Delivery $delivery, DeliveryAction $action): RedirectResponse
    {
        $this->authorize('update', $delivery);

        $manual = ['chk_quality_check', 'chk_insurance', 'chk_rto_papers_signed', 'chk_accessories', 'chk_cleaned', 'chk_documents_prepared'];
        $rules = [];
        foreach ($manual as $field) {
            $rules[$field] = ['sometimes', 'boolean'];
        }
        $data = $request->validate($rules);

        $action->setManualChecks($delivery, $data);

        return back()->with('success', 'Checklist updated.');
    }

    public function approve(Request $request, Delivery $delivery, DeliveryAction $action): RedirectResponse
    {
        $this->authorize('approve', $delivery);

        $action->approve($delivery, $request->user());

        return back()->with('success', 'Delivery approved. Vehicle is now delivery-pending.');
    }

    public function complete(Request $request, Delivery $delivery, DeliveryAction $action): RedirectResponse
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

        $action->complete($delivery, $request->user(), $data);

        return back()->with('success', 'Vehicle delivered. RTO transfer case created.');
    }

    public function challan(Request $request, Delivery $delivery, DocumentGenerator $generator): RedirectResponse
    {
        abort_unless($request->user()->can('deliveries.print'), 403);

        if ($delivery->status !== DeliveryStatus::Delivered) {
            return back()->with('error', 'The challan is available only after handover.');
        }

        $delivery->load(['customer', 'vehicle', 'booking', 'branch']);

        $generator->generate(
            templateKey: 'delivery_challan',
            view: 'documents.delivery_challan',
            data: ['delivery' => $delivery],
            subject: $delivery,
            generatedBy: $request->user(),
            referencePrefix: 'DC',
        );

        return back()->with('success', 'Delivery challan generated.');
    }

    /** @return array<int, array{key: string, label: string, auto: bool}> */
    private function checklistMeta(): array
    {
        return [
            ['key' => 'chk_booking_confirmed', 'label' => 'Booking confirmed', 'auto' => true],
            ['key' => 'chk_kyc_verified', 'label' => 'KYC verified', 'auto' => true],
            ['key' => 'chk_payment_complete', 'label' => 'Payment complete', 'auto' => true],
            ['key' => 'chk_finance_disbursed', 'label' => 'Finance disbursed', 'auto' => true],
            ['key' => 'chk_quality_check', 'label' => 'Pre-delivery quality check', 'auto' => false],
            ['key' => 'chk_insurance', 'label' => 'Insurance in buyer name', 'auto' => false],
            ['key' => 'chk_rto_papers_signed', 'label' => 'RTO transfer papers signed', 'auto' => false],
            ['key' => 'chk_accessories', 'label' => 'Promised accessories fitted', 'auto' => false],
            ['key' => 'chk_cleaned', 'label' => 'Cleaned & detailed', 'auto' => false],
            ['key' => 'chk_documents_prepared', 'label' => 'Handover documents prepared', 'auto' => false],
        ];
    }
}
