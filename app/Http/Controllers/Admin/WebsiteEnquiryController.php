<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Branches\Models\Branch;
use App\Domain\PublicWebsite\Enums\EnquiryType;
use App\Domain\PublicWebsite\Models\PublicEnquiry;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteEnquiryController extends Controller
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('public-website.view'), 403);

        $enquiries = PublicEnquiry::query()
            ->with(['vehicle:id,stock_number,make,model', 'branch:id,name', 'assignee:id,name', 'purchaseLead:id,lead_number'])
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), ['branch' => 'branch_id', 'assigned' => 'assigned_to', 'owner' => 'assigned_to']))
            ->when($request->string('type')->toString(), fn ($q, $t) => $q->where('type', $t))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->integer('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PublicEnquiry $e) => [
                'id' => $e->id,
                'enquiry_number' => $e->enquiry_number,
                'type' => $e->type->value,
                'type_label' => $e->type->label(),
                'name' => $e->name,
                'mobile' => $e->mobile,
                'city' => $e->city,
                'message' => $e->message,
                'vehicle' => $e->vehicle?->only(['id', 'stock_number', 'make', 'model']),
                'branch' => $e->branch?->only(['id', 'name']),
                'purchase_lead' => $e->purchaseLead?->only(['id', 'lead_number']),
                'status' => $e->status,
                'created_at' => $e->created_at->toDateTimeString(),
            ]);

        return Inertia::render('admin/enquiries/Index', [
            'enquiries' => $enquiries,
            'types' => array_map(fn (EnquiryType $t) => ['value' => $t->value, 'label' => $t->label()], EnquiryType::cases()),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'type' => $request->string('type')->toString() ?: null,
                'status' => $request->string('status')->toString() ?: null,
                'branch_id' => $request->integer('branch_id') ?: null,
            ],
            'can' => ['update' => $request->user()->can('public-website.update')],
        ]);
    }

    public function update(Request $request, PublicEnquiry $enquiry): RedirectResponse
    {
        abort_unless($request->user()->can('public-website.update'), 403);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:new,contacted,converted,closed,spam'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $enquiry->update([
            'status' => $data['status'],
            'remarks' => $data['remarks'] ?? $enquiry->remarks,
            'assigned_to' => $enquiry->assigned_to ?? $request->user()->id,
        ]);

        return back()->with('success', 'Enquiry updated.');
    }
}
