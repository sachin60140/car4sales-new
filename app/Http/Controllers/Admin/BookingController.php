<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Bookings\Actions\CancelBookingAction;
use App\Domain\Bookings\Actions\ConfirmBookingAction;
use App\Domain\Bookings\Actions\CreateBookingAction;
use App\Domain\Bookings\Actions\RefundAction;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Models\Booking;
use App\Domain\Bookings\Models\BookingCancellation;
use App\Domain\Bookings\Models\BookingPayment;
use App\Domain\Bookings\Models\Refund;
use App\Domain\Finance\Models\FinanceApplication;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Payments\Models\CustomerLedger;
use App\Domain\Payments\Models\Invoice;
use App\Domain\Payments\Services\InvoiceService;
use App\Domain\Payments\Services\PaymentService;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Booking::class);

        $bookings = Booking::query()
            ->with(['customer:id,name,mobile', 'vehicle:id,stock_number,make,model', 'salesExecutive:id,name', 'branch:id,name'])
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), ['branch' => 'branch_id', 'assigned' => 'sales_executive_id', 'owner' => 'created_by']))
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('booking_number', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('mobile', 'like', "%{$s}%"))))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Booking $b) => [
                'id' => $b->id,
                'booking_number' => $b->booking_number,
                'customer' => $b->customer?->only(['id', 'name', 'mobile']),
                'vehicle' => $b->vehicle?->only(['id', 'stock_number', 'make', 'model']),
                'selling_price' => $b->selling_price,
                'status' => $b->status->value,
                'status_label' => $b->status->label(),
                'sales_executive' => $b->salesExecutive?->only(['id', 'name']),
                'branch' => $b->branch?->only(['id', 'name']),
                'created_at' => $b->created_at->toDateString(),
            ]);

        return Inertia::render('admin/bookings/Index', [
            'bookings' => $bookings,
            'statuses' => BookingStatus::options(),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString() ?: null,
            ],
            'can' => ['create' => $request->user()->can('create', Booking::class)],
        ]);
    }

    public function store(Request $request, CreateBookingAction $action): RedirectResponse
    {
        $this->authorize('create', Booking::class);

        $data = $request->validate([
            'sales_lead_id' => ['required', 'integer', 'exists:sales_leads,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'booking_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_mode' => ['required', 'string', 'in:cash,finance'],
            'exchange_adjustment' => ['nullable', 'numeric', 'min:0'],
            'delivery_promised_at' => ['nullable', 'date'],
            'terms' => ['nullable', 'string', 'max:2000'],
        ]);

        $lead = SalesLead::query()->findOrFail($data['sales_lead_id']);
        $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
        $booking = $action->execute($lead, $vehicle, $data, $request->user());

        return redirect()->route('admin.bookings.show', $booking)->with('success', "Booking {$booking->booking_number} created.");
    }

    public function show(Request $request, Booking $booking): Response
    {
        $this->authorize('view', $booking);

        $booking->load([
            'customer', 'vehicle:id,stock_number,make,model,slug,minimum_selling_price', 'lead:id,lead_number',
            'salesExecutive:id,name', 'branch:id,name', 'payments.receiver:id,name',
            'cancellations.requester:id,name', 'refunds', 'statusHistories.changer:id,name',
            'approvalRequest.steps',
        ]);

        $ledger = CustomerLedger::query()
            ->where('booking_id', $booking->id)
            ->with('entries.poster:id,name')->first();

        $financeApplication = FinanceApplication::query()
            ->where('booking_id', $booking->id)->first(['id', 'application_number', 'status']);

        $invoice = Invoice::query()
            ->where('booking_id', $booking->id)->first(['id', 'invoice_number', 'total', 'generated_document_id']);

        return Inertia::render('admin/bookings/Show', [
            'booking' => $booking,
            'netPayable' => $booking->netPayable(),
            'paidAmount' => $booking->paidAmount(),
            'ledger' => $ledger === null ? null : [
                'outstanding' => $ledger->outstanding(),
                'entries' => $ledger->entries->map(fn ($e) => [
                    'id' => $e->id, 'type' => $e->type, 'head' => $e->head->value,
                    'amount' => $e->amount, 'is_reversal' => $e->isReversal(),
                    'remarks' => $e->remarks, 'posted_at' => $e->posted_at->toDateTimeString(),
                ]),
            ],
            'financeApplication' => $financeApplication,
            'invoice' => $invoice === null ? null : [
                'invoice_number' => $invoice->invoice_number,
                'total' => $invoice->total,
                'download' => $invoice->generated_document_id ? "/admin/documents/{$invoice->generated_document_id}/download" : null,
            ],
            'allowedTransitions' => array_map(
                fn (BookingStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                $booking->status->allowedTransitions(),
            ),
            'can' => [
                'update' => $request->user()->can('update', $booking),
                'confirm' => $request->user()->can('update', $booking),
                'cancel' => $request->user()->can('cancel', $booking),
                'approveCancel' => $request->user()->can('bookings.approve'),
                'refund' => $request->user()->can('payments.reverse-payment') || $request->user()->hasRole('Accounts Manager') || $request->user()->hasRole('Super Admin'),
                'reversePayment' => $request->user()->can('payments.reverse-payment') || $request->user()->hasRole('Accounts Manager') || $request->user()->hasRole('Super Admin'),
                'invoice' => $request->user()->can('bookings.print'),
                'finance' => $request->user()->can('finance.create'),
            ],
        ]);
    }

    public function generateInvoice(Request $request, Booking $booking, InvoiceService $invoices): RedirectResponse
    {
        abort_unless($request->user()->can('bookings.print'), 403);

        $invoices->generate($booking, $request->user());

        return back()->with('success', 'Invoice generated.');
    }

    public function confirm(Request $request, Booking $booking, ConfirmBookingAction $action): RedirectResponse
    {
        $this->authorize('update', $booking);

        $action->execute($booking, $request->user());

        return back()->with('success', 'Booking processed.');
    }

    public function payment(Request $request, Booking $booking, PaymentService $payments): RedirectResponse
    {
        $this->authorize('update', $booking);

        $data = $request->validate([
            'type' => ['required', 'string', 'in:token,booking,advance,balance'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'in:cash,upi,card,bank_transfer,cheque'],
            'account_id' => ['nullable', 'integer', 'exists:payment_accounts,id'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $payments->record($booking, $data, $request->user());

        return back()->with('success', 'Payment recorded.');
    }

    public function reversePayment(Request $request, BookingPayment $payment, PaymentService $payments): RedirectResponse
    {
        abort_unless($request->user()->can('payments.reverse-payment') || $request->user()->hasRole('Accounts Manager') || $request->user()->hasRole('Super Admin'), 403);

        $data = $request->validate(['remarks' => ['required', 'string', 'max:500']]);
        $payments->reverse($payment, $request->user(), $data['remarks']);

        return back()->with('success', 'Payment reversed.');
    }

    public function requestCancellation(Request $request, Booking $booking, CancelBookingAction $action): RedirectResponse
    {
        $this->authorize('cancel', $booking);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'forfeit_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $action->request($booking, $data['reason'], (float) ($data['refund_amount'] ?? 0), (float) ($data['forfeit_amount'] ?? 0), $request->user());

        return back()->with('success', 'Cancellation requested.');
    }

    public function approveCancellation(Request $request, BookingCancellation $cancellation, CancelBookingAction $action): RedirectResponse
    {
        abort_unless($request->user()->can('bookings.approve'), 403);

        $action->approve($cancellation, $request->user());

        return back()->with('success', 'Cancellation approved and vehicle released.');
    }

    public function initiateRefund(Request $request, BookingCancellation $cancellation, RefundAction $action): RedirectResponse
    {
        abort_unless($request->user()->can('bookings.approve') || $request->user()->hasRole('Accounts Manager'), 403);

        $action->initiate($cancellation, $request->user());

        return back()->with('success', 'Refund raised for approval.');
    }

    public function payRefund(Request $request, Refund $refund, RefundAction $action): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Accounts Manager') || $request->user()->hasRole('Super Admin'), 403);

        $data = $request->validate([
            'method' => ['required', 'string', 'in:cash,upi,bank_transfer,cheque'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $action->pay($refund, $request->user(), $data['method'], $data['reference'] ?? null);

        return back()->with('success', 'Refund paid.');
    }
}
