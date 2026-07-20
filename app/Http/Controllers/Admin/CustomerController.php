<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Customers\Models\Customer;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
        ]);
    }

    public function show(Request $request, Customer $customer): Response
    {
        $this->authorize('view', $customer);

        $customer->load(['branch:id,name', 'documents', 'salesLeads' => fn ($q) => $q->with('interestedVehicle:id,stock_number,make,model')->latest()]);

        return Inertia::render('admin/customers/Show', [
            'customer' => $customer,
            'canViewKyc' => $request->user()->can('customers.view-kyc'),
        ]);
    }
}
