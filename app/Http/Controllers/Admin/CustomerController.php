<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Actions\CustomerKycAction;
use App\Domain\Customers\Actions\SaveCustomerAction;
use App\Domain\Customers\Models\Customer;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::query()
            ->with('branch:id,name')
            ->withCount('salesLeads')
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), ['branch' => 'branch_id', 'owner' => 'id']))
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")->orWhere('mobile', 'like', "%{$s}%")->orWhere('customer_code', 'like', "%{$s}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/customers/Index', [
            'customers' => $customers,
            'filters' => ['search' => $request->string('search')->toString()],
            'can' => ['create' => $request->user()->can('create', Customer::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Customer::class);

        return Inertia::render('admin/customers/Form', [
            'customer' => null,
            'branches' => $this->branchOptions(),
            'canViewKyc' => $request->user()->can('customers.view-kyc'),
        ]);
    }

    public function store(Request $request, SaveCustomerAction $action): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $customer = $action->execute(null, $this->kycFiltered($request, $this->validated($request, null)));

        return redirect()->route('admin.customers.show', $customer)->with('success', "Customer {$customer->customer_code} added.");
    }

    public function show(Request $request, Customer $customer): Response
    {
        $this->authorize('view', $customer);

        $canViewKyc = $request->user()->can('customers.view-kyc');

        $customer->load([
            'branch:id,name',
            'documents.verifier:id,name',
            'salesLeads' => fn ($q) => $q->with('interestedVehicle:id,stock_number,make,model')->latest(),
        ]);

        if (! $canViewKyc) {
            $customer->makeHidden(['aadhaar_number', 'pan_number']);
        }

        return Inertia::render('admin/customers/Show', [
            'customer' => $customer,
            'canViewKyc' => $canViewKyc,
            'kyc' => $canViewKyc ? [
                'rows' => $this->kycRows($customer),
                'documentStatuses' => [
                    ['value' => 'verified', 'label' => 'Verified'],
                    ['value' => 'rejected', 'label' => 'Rejected'],
                    ['value' => 'received', 'label' => 'Received'],
                ],
            ] : null,
            'can' => ['update' => $request->user()->can('update', $customer)],
        ]);
    }

    public function uploadDocument(Request $request, Customer $customer, CustomerKycAction $action): RedirectResponse
    {
        $this->authorize('view', $customer);
        abort_unless($request->user()->can('customers.view-kyc'), 403);

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Customer::kycDocumentCatalog()))],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
            'number' => ['nullable', 'string', 'max:100'],
        ]);

        $action->uploadDocument($customer, $data['type'], $request->file('file'), $data['number'] ?? null, $request->user());

        return back()->with('success', 'Document uploaded.');
    }

    public function verifyDocument(Request $request, Customer $customer, CustomerKycAction $action): RedirectResponse
    {
        $this->authorize('view', $customer);
        abort_unless($request->user()->can('customers.view-kyc'), 403);

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Customer::kycDocumentCatalog()))],
            'status' => ['required', Rule::in(['verified', 'rejected', 'received'])],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $action->verifyDocument($customer, $data['type'], $data['status'], $data['rejection_reason'] ?? null, $request->user());

        return back()->with('success', 'Document verification updated.');
    }

    public function edit(Request $request, Customer $customer): Response
    {
        $this->authorize('update', $customer);

        $canViewKyc = $request->user()->can('customers.view-kyc');

        $fields = ['id', 'customer_code', 'name', 'mobile', 'alt_mobile', 'email',
            'address', 'city', 'state', 'pin_code', 'occupation', 'dob', 'branch_id'];
        if ($canViewKyc) {
            $fields[] = 'aadhaar_number';
            $fields[] = 'pan_number';
        }

        return Inertia::render('admin/customers/Form', [
            'customer' => $customer->only($fields),
            'branches' => $this->branchOptions(),
            'canViewKyc' => $canViewKyc,
        ]);
    }

    public function update(Request $request, Customer $customer, SaveCustomerAction $action): RedirectResponse
    {
        $this->authorize('update', $customer);

        $action->execute($customer, $this->kycFiltered($request, $this->validated($request, $customer)));

        return redirect()->route('admin.customers.show', $customer)->with('success', 'Customer updated.');
    }

    /**
     * Drop KYC identity numbers unless the user may view/manage KYC, so they can
     * never be written by an unauthorised user (even via a crafted request).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function kycFiltered(Request $request, array $data): array
    {
        if (! $request->user()->can('customers.view-kyc')) {
            unset($data['aadhaar_number'], $data['pan_number']);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Customer $customer): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:20', Rule::unique('customers', 'mobile')->ignore($customer?->id)->withoutTrashed()],
            'alt_mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pin_code' => ['nullable', 'string', 'max:10'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'dob' => ['nullable', 'date', 'before:today'],
            'aadhaar_number' => ['nullable', 'string', 'regex:/^\d{12}$/'],
            'pan_number' => ['nullable', 'string', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->withoutTrashed()],
        ], [
            'aadhaar_number.regex' => 'Aadhaar must be a 12-digit number.',
            'pan_number.regex' => 'PAN must look like ABCDE1234F.',
        ]);
    }

    /**
     * @return Collection<int, Branch>
     */
    private function branchOptions()
    {
        return Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * One row per catalog document type with its uploaded file (if any), for the
     * customer KYC document table.
     *
     * @return array<int, array<string, mixed>>
     */
    private function kycRows(Customer $customer): array
    {
        $byType = $customer->documents->keyBy('type');

        return collect(Customer::kycDocumentCatalog())->map(fn (array $def, string $type) => [
            'type' => $type,
            'label' => $def['label'],
            'group' => $def['group'],
            'document' => ($doc = $byType->get($type)) ? [
                'id' => $doc->id,
                'status' => $doc->status,
                'number' => $doc->number,
                'rejection_reason' => $doc->rejection_reason,
                'verified_by_name' => $doc->verifier?->name,
                'uploaded_at' => $doc->created_at?->toDateString(),
            ] : null,
        ])->values()->all();
    }
}
