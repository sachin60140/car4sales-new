<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Bookings\Models\Booking;
use App\Domain\Finance\Actions\FinanceApplicationAction;
use App\Domain\Finance\Enums\FinanceStatus;
use App\Domain\Finance\Models\FinanceApplication;
use App\Domain\Finance\Models\Lender;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FinanceApplication::class);

        $applications = FinanceApplication::query()
            ->with(['customer:id,name,mobile', 'lender:id,name', 'booking:id,booking_number', 'assignee:id,name'])
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), ['branch' => 'branch_id', 'assigned' => 'assigned_to', 'owner' => 'created_by']))
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('application_number', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('mobile', 'like', "%{$s}%"))))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (FinanceApplication $f) => [
                'id' => $f->id,
                'application_number' => $f->application_number,
                'customer' => $f->customer?->only(['id', 'name', 'mobile']),
                'lender' => $f->lender?->only(['id', 'name']),
                'booking' => $f->booking?->only(['id', 'booking_number']),
                'loan_amount' => $f->loan_amount,
                'sanction_amount' => $f->sanction_amount,
                'status' => $f->status->value,
                'status_label' => $f->status->label(),
            ]);

        return Inertia::render('admin/finance/Index', [
            'applications' => $applications,
            'statuses' => FinanceStatus::options(),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString() ?: null,
            ],
        ]);
    }

    public function store(Request $request, FinanceApplicationAction $action): RedirectResponse
    {
        $this->authorize('create', FinanceApplication::class);

        $data = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'lender_id' => ['nullable', 'integer', 'exists:lenders,id'],
            'loan_amount' => ['required', 'numeric', 'min:0'],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'tenure_months' => ['nullable', 'integer', 'min:1'],
            'employer' => ['nullable', 'string', 'max:255'],
        ]);

        $booking = Booking::query()->findOrFail($data['booking_id']);
        $application = $action->create($booking, $data, $request->user());

        return redirect()->route('admin.finance.show', $application)->with('success', "Finance file {$application->application_number} created.");
    }

    public function show(Request $request, FinanceApplication $finance): Response
    {
        $this->authorize('view', $finance);

        $finance->load([
            'customer', 'lender:id,name', 'booking:id,booking_number,selling_price,payment_mode',
            'assignee:id,name', 'disbursements.application', 'statusHistories.changer:id,name',
        ]);

        return Inertia::render('admin/finance/Show', [
            'application' => $finance,
            'lenders' => Lender::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'allowedTransitions' => array_values(array_map(
                fn (FinanceStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                // Disbursed is reached through "Record Disbursement", not this dropdown.
                array_filter(
                    $finance->status->allowedTransitions(),
                    fn (FinanceStatus $s) => ! $s->requiresDedicatedAction(),
                ),
            )),
            'can' => [
                'update' => $request->user()->can('update', $finance),
                'disburse' => $request->user()->can('update', $finance),
            ],
        ]);
    }

    public function transition(Request $request, FinanceApplication $finance, FinanceApplicationAction $action): RedirectResponse
    {
        $this->authorize('update', $finance);

        $data = $request->validate([
            'status' => ['required', Rule::enum(FinanceStatus::class)],
            'lender_id' => ['nullable', 'integer', 'exists:lenders,id'],
            'lender_application_number' => ['nullable', 'string', 'max:255'],
            'sanction_amount' => ['nullable', 'numeric', 'min:0'],
            'emi' => ['nullable', 'numeric', 'min:0'],
            'interest_rate' => ['nullable', 'numeric', 'min:0'],
            'tenure_months' => ['nullable', 'integer', 'min:1'],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
            'queries' => ['nullable', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $target = FinanceStatus::from($data['status']);

        // Disbursement is completed via "Record Disbursement" — that creates the
        // disbursement + posts the ledger credit. The status control must not set it.
        if ($target->requiresDedicatedAction()) {
            return back()->with('error', 'Use “Record Disbursement” to disburse — that step creates the disbursement and posts the customer-ledger credit.');
        }

        $action->transition($finance, $target, $data, $request->user());

        return back()->with('success', 'Finance status updated.');
    }

    public function disburse(Request $request, FinanceApplication $finance, FinanceApplicationAction $action): RedirectResponse
    {
        $this->authorize('update', $finance);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'utr' => ['nullable', 'string', 'max:255'],
        ]);

        $action->disburse($finance, (float) $data['amount'], $data['utr'] ?? null, $request->user());

        return back()->with('success', 'Disbursement recorded and posted to the customer ledger.');
    }
}
