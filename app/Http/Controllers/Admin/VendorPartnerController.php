<?php

namespace App\Http\Controllers\Admin;

use App\Domain\VendorSubmissions\Actions\VendorPartnerKycAction;
use App\Domain\VendorSubmissions\Actions\VendorRegistrationAction;
use App\Domain\VendorSubmissions\Enums\VendorProfileStatus;
use App\Domain\VendorSubmissions\Models\VendorProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class VendorPartnerController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', VendorProfile::class);

        $partners = VendorProfile::query()
            ->with(['user:id,name,email'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->orderByRaw("CASE status WHEN 'pending_activation' THEN 0 ELSE 1 END"))
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('company_name', 'like', "%{$s}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (VendorProfile $p) => [
                'id' => $p->id,
                'name' => $p->user?->name,
                'email' => $p->user?->email,
                'company_name' => $p->company_name,
                'phone' => $p->phone,
                'city' => $p->city,
                'gst_number' => $p->gst_number,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'kyc_status' => $p->kyc_status,
                'created_at' => $p->created_at->toDateString(),
            ]);

        return Inertia::render('admin/vendor-partners/Index', [
            'partners' => $partners,
            'statuses' => VendorProfileStatus::options(),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString() ?: null,
            ],
            'can' => [
                'activate' => $request->user()->can('vendor-partners.activate'),
                'create' => $request->user()->can('vendor-partners.create'),
                'update' => $request->user()->can('vendor-partners.update'),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', VendorProfile::class);

        return Inertia::render('admin/vendor-partners/Form', [
            'partner' => null,
            'kyc' => null,
        ]);
    }

    public function store(Request $request, VendorRegistrationAction $action): RedirectResponse
    {
        $this->authorize('create', VendorProfile::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->withoutTrashed()],
            'password' => ['required', 'string', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'gst_number' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $action->createByAdmin($data, $request->user());

        // Continue to the edit screen so the admin can add KYC documents next.
        return redirect()
            ->route('admin.vendor-partners.edit', $user->vendorProfile)
            ->with('success', 'Vendor partner added — upload their KYC documents to activate them.');
    }

    public function edit(VendorProfile $vendorProfile): Response
    {
        $this->authorize('update', $vendorProfile);

        $vendorProfile->load(['user:id,name,email,phone', 'documents.verifier:id,name']);

        return Inertia::render('admin/vendor-partners/Form', [
            'partner' => [
                'id' => $vendorProfile->id,
                'name' => $vendorProfile->user?->name,
                'email' => $vendorProfile->user?->email,
                'phone' => $vendorProfile->phone ?? $vendorProfile->user?->phone,
                'company_name' => $vendorProfile->company_name,
                'contact_person' => $vendorProfile->contact_person,
                'city' => $vendorProfile->city,
                'gst_number' => $vendorProfile->gst_number,
                'status' => $vendorProfile->status->value,
                'status_label' => $vendorProfile->status->label(),
            ],
            'kyc' => [
                'rows' => $vendorProfile->kycRows(),
                'status' => $vendorProfile->kyc_status,
                'documentStatuses' => [
                    ['value' => 'verified', 'label' => 'Verified'],
                    ['value' => 'rejected', 'label' => 'Rejected'],
                    ['value' => 'pending', 'label' => 'Pending'],
                ],
            ],
        ]);
    }

    public function uploadDocument(Request $request, VendorProfile $vendorProfile, VendorPartnerKycAction $action): RedirectResponse
    {
        $this->authorize('update', $vendorProfile);

        $data = $request->validate([
            'type' => ['required', Rule::in(VendorProfile::allMediaTypes())],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
            'number' => ['nullable', 'string', 'max:100'],
        ]);

        $action->uploadDocument($vendorProfile, $data['type'], $request->file('file'), $data['number'] ?? null, $request->user());

        return back()->with('success', 'Document uploaded.');
    }

    public function verifyDocument(Request $request, VendorProfile $vendorProfile, VendorPartnerKycAction $action): RedirectResponse
    {
        $this->authorize('update', $vendorProfile);

        $data = $request->validate([
            'type' => ['required', Rule::in(VendorProfile::allMediaTypes())],
            'status' => ['required', Rule::in(['verified', 'rejected', 'pending'])],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $action->verifyDocument($vendorProfile, $data['type'], $data['status'], $data['remarks'] ?? null, $request->user());

        return back()->with('success', 'Document verification updated.');
    }

    public function update(Request $request, VendorProfile $vendorProfile, VendorRegistrationAction $action): RedirectResponse
    {
        $this->authorize('update', $vendorProfile);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($vendorProfile->user_id)->withoutTrashed()],
            'password' => ['nullable', 'string', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'gst_number' => ['nullable', 'string', 'max:20'],
        ]);

        $action->updateProfile($vendorProfile, $data);

        return redirect()->route('admin.vendor-partners.index')->with('success', 'Vendor partner updated.');
    }

    public function setStatus(Request $request, VendorProfile $vendorProfile, VendorRegistrationAction $action): RedirectResponse
    {
        $this->authorize('activate', $vendorProfile);

        $data = $request->validate([
            'status' => ['required', Rule::enum(VendorProfileStatus::class)],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $action->setStatus($vendorProfile, VendorProfileStatus::from($data['status']), $request->user(), $data['remarks'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', 'Vendor status updated.');
    }
}
