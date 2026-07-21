<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Branches\Models\Branch;
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
        ]);
    }

    public function store(Request $request, SaveCustomerAction $action): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $customer = $action->execute(null, $this->validated($request, null));

        return redirect()->route('admin.customers.show', $customer)->with('success', "Customer {$customer->customer_code} added.");
    }

    public function show(Request $request, Customer $customer): Response
    {
        $this->authorize('view', $customer);

        $customer->load(['branch:id,name', 'documents', 'salesLeads' => fn ($q) => $q->with('interestedVehicle:id,stock_number,make,model')->latest()]);

        return Inertia::render('admin/customers/Show', [
            'customer' => $customer,
            'canViewKyc' => $request->user()->can('customers.view-kyc'),
            'can' => ['update' => $request->user()->can('update', $customer)],
        ]);
    }

    public function edit(Request $request, Customer $customer): Response
    {
        $this->authorize('update', $customer);

        return Inertia::render('admin/customers/Form', [
            'customer' => $customer->only([
                'id', 'customer_code', 'name', 'mobile', 'alt_mobile', 'email',
                'address', 'city', 'state', 'pin_code', 'occupation', 'dob', 'branch_id',
            ]),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function update(Request $request, Customer $customer, SaveCustomerAction $action): RedirectResponse
    {
        $this->authorize('update', $customer);

        $action->execute($customer, $this->validated($request, $customer));

        return redirect()->route('admin.customers.show', $customer)->with('success', 'Customer updated.');
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
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->withoutTrashed()],
        ]);
    }

    /**
     * @return Collection<int, Branch>
     */
    private function branchOptions()
    {
        return Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }
}
