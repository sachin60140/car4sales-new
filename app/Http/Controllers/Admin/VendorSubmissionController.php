<?php

namespace App\Http\Controllers\Admin;

use App\Domain\VendorSubmissions\Actions\VendorSettlementAction;
use App\Domain\VendorSubmissions\Actions\VendorSubmissionAction;
use App\Domain\VendorSubmissions\Enums\SettlementStatus;
use App\Domain\VendorSubmissions\Enums\SubmissionStatus;
use App\Domain\VendorSubmissions\Models\VendorSubmission;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorSubmissionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', VendorSubmission::class);

        $submissions = VendorSubmission::query()
            ->with(['vendor:id,name'])
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('submission_number', 'like', "%{$s}%")
                ->orWhere('make', 'like', "%{$s}%")
                ->orWhere('model', 'like', "%{$s}%")
                ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$s}%"))))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->orderByRaw("CASE status WHEN 'pending_review' THEN 0 ELSE 1 END"))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (VendorSubmission $s) => [
                'id' => $s->id,
                'submission_number' => $s->submission_number,
                'title' => $s->title(),
                'vendor' => $s->vendor?->only(['id', 'name']),
                'expected_amount' => $s->expected_amount,
                'overall_rating' => $s->overall_rating,
                'status' => $s->status->value,
                'status_label' => $s->status->label(),
                'created_at' => $s->created_at->toDateString(),
            ]);

        return Inertia::render('admin/vendor-submissions/Index', [
            'submissions' => $submissions,
            'statuses' => SubmissionStatus::options(),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString() ?: null,
            ],
        ]);
    }

    public function show(Request $request, VendorSubmission $vendorSubmission): Response
    {
        $this->authorize('review', $vendorSubmission);

        $vendorSubmission->load([
            'vendor:id,name,phone,email', 'vendor.vendorProfile:id,user_id,company_name,phone',
            'items', 'media', 'reviewer:id,name', 'kycApprovedBy:id,name',
            'purchaseLead:id,lead_number,status', 'branch:id,name', 'vehicle:id,stock_number',
        ]);

        return Inertia::render('admin/vendor-submissions/Show', [
            'submission' => [
                ...$vendorSubmission->only([
                    'id', 'submission_number', 'make', 'model', 'variant', 'manufacturing_year',
                    'registration_number', 'registration_state', 'fuel_type', 'transmission', 'color',
                    'odometer_km', 'ownership_serial', 'expected_amount', 'overall_rating', 'overall_remark',
                    'review_remarks',
                ]),
                'status' => $vendorSubmission->status->value,
                'status_label' => $vendorSubmission->status->label(),
                'vendor' => [
                    'name' => $vendorSubmission->vendor?->name,
                    'company' => $vendorSubmission->vendor?->vendorProfile?->company_name,
                    'phone' => $vendorSubmission->vendor?->vendorProfile?->phone ?? $vendorSubmission->vendor?->phone,
                    'email' => $vendorSubmission->vendor?->email,
                ],
                'branch' => $vendorSubmission->branch?->only(['id', 'name']),
                'reviewer' => $vendorSubmission->reviewer?->only(['id', 'name']),
                'purchase_lead' => $vendorSubmission->purchaseLead?->only(['id', 'lead_number', 'status']),
                'items' => $vendorSubmission->items->map(fn ($i) => [
                    'id' => $i->id, 'section' => $i->section, 'label' => $i->label,
                    'result' => $i->result->value, 'rating' => $i->rating, 'remarks' => $i->remarks,
                ]),
                'gallery' => $vendorSubmission->media->where('type', 'gallery')->values()->map(fn ($m) => ['id' => $m->id, 'url' => route('submission-media.view', $m)]),
                'damage' => $vendorSubmission->media->where('type', 'damage')->values()->map(fn ($m) => ['id' => $m->id, 'url' => route('submission-media.view', $m)]),
                // Settlement.
                'settlement_status' => $vendorSubmission->settlement_status->value,
                'settlement_label' => $vendorSubmission->settlement_status->label(),
                'agreement_url' => $vendorSubmission->settlement_status->agreementAvailable() ? route('submission-agreement.download', $vendorSubmission) : null,
                // Owner (seller) KYC — captured after approval, kept off the purchase lead.
                'owner' => $vendorSubmission->owner_name ? [
                    'name' => $vendorSubmission->owner_name,
                    'phone' => $vendorSubmission->owner_phone,
                    'email' => $vendorSubmission->owner_email,
                    'address' => $vendorSubmission->owner_address,
                    'pan' => $vendorSubmission->owner_pan,
                ] : null,
                'documents' => $this->documentRows($vendorSubmission),
                'kyc_remarks' => $vendorSubmission->kyc_remarks,
                'kyc_approved_by' => $vendorSubmission->kycApprovedBy?->name,
                'kyc_approved_at' => $vendorSubmission->kyc_approved_at?->toDateString(),
                'bank' => $vendorSubmission->bank_account_number ? [
                    'account_name' => $vendorSubmission->bank_account_name,
                    'account_number' => $vendorSubmission->bank_account_number,
                    'ifsc' => $vendorSubmission->bank_ifsc,
                    'bank_name' => $vendorSubmission->bank_name,
                ] : null,
                'cheque' => ($c = $vendorSubmission->media->firstWhere('type', 'cancelled_cheque')) ? ['id' => $c->id, 'url' => route('submission-media.view', $c)] : null,
                'payment' => in_array($vendorSubmission->settlement_status, [SettlementStatus::Paid, SettlementStatus::Stocked], true) ? [
                    'amount' => $vendorSubmission->payment_amount,
                    'mode' => $vendorSubmission->payment_mode,
                    'reference' => $vendorSubmission->payment_reference,
                    'date' => $vendorSubmission->payment_date?->toDateString(),
                ] : null,
                'payment_proof' => ($p = $vendorSubmission->media->firstWhere('type', 'payment_proof')) ? ['id' => $p->id, 'url' => route('submission-media.view', $p)] : null,
                // Possession → stock.
                'possession' => $vendorSubmission->possession,
                'vehicle' => $vendorSubmission->vehicle ? [
                    'id' => $vendorSubmission->vehicle->id,
                    'stock_number' => $vendorSubmission->vehicle->stock_number,
                ] : null,
            ],
            'docLabels' => VendorSubmission::REQUIRED_KYC_DOCS,
            'can' => [
                'review' => $vendorSubmission->status === SubmissionStatus::PendingReview,
                'approveKyc' => $vendorSubmission->settlement_status === SettlementStatus::KycSubmitted,
                'recordPayment' => $vendorSubmission->settlement_status === SettlementStatus::PaymentRequested,
                'confirmPossession' => $vendorSubmission->settlement_status === SettlementStatus::Paid
                    && $request->user()->can('possessions.create'),
            ],
        ]);
    }

    public function confirmPossession(Request $request, VendorSubmission $vendorSubmission, VendorSettlementAction $action): RedirectResponse
    {
        $this->authorize('review', $vendorSubmission);
        abort_unless($request->user()->can('possessions.create'), 403);

        $checklist = $request->validate([
            'vehicle_received' => ['required', 'boolean'],
            'original_rc_received' => ['boolean'],
            'insurance_received' => ['boolean'],
            'puc_received' => ['boolean'],
            'noc_received' => ['boolean'],
            'form_35_received' => ['boolean'],
            'main_key' => ['boolean'],
            'spare_key' => ['boolean'],
            'service_book' => ['boolean'],
            'tool_kit' => ['boolean'],
            'spare_wheel' => ['boolean'],
            'accessories' => ['boolean'],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'fuel_level' => ['nullable', 'string', 'max:20'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $action->confirmPossession($vendorSubmission, $checklist, $request->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Possession confirmed. Stock '.$result['vehicle']->stock_number.' created.');
    }

    /** Owner-KYC documents grouped by type (+ extras), for review. */
    private function documentRows(VendorSubmission $s): array
    {
        $docs = [];
        foreach (array_keys(VendorSubmission::REQUIRED_KYC_DOCS) as $type) {
            $m = $s->media->firstWhere('type', $type);
            $docs[$type] = $m ? ['id' => $m->id, 'url' => route('submission-media.view', $m)] : null;
        }
        $docs['extra'] = $s->media->where('type', 'other_doc')->values()->map(fn ($m) => ['id' => $m->id, 'url' => route('submission-media.view', $m)]);

        return $docs;
    }

    public function approveKyc(Request $request, VendorSubmission $vendorSubmission, VendorSettlementAction $action): RedirectResponse
    {
        $this->authorize('review', $vendorSubmission);

        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:1000']]);

        try {
            $action->approveOwnerKyc($vendorSubmission, $request->user(), $data['remarks'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Owner documents verified — the agreement is now available to the vendor.');
    }

    public function rejectKyc(Request $request, VendorSubmission $vendorSubmission, VendorSettlementAction $action): RedirectResponse
    {
        $this->authorize('review', $vendorSubmission);

        $data = $request->validate(['remarks' => ['required', 'string', 'max:1000']]);

        try {
            $action->rejectOwnerKyc($vendorSubmission, $request->user(), $data['remarks']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Documents sent back to the vendor for correction.');
    }

    public function recordPayment(Request $request, VendorSubmission $vendorSubmission, VendorSettlementAction $action): RedirectResponse
    {
        $this->authorize('review', $vendorSubmission);

        $data = $request->validate([
            'payment_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_mode' => ['required', 'string', 'in:neft,upi,cheque,cash,rtgs'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['nullable', 'date'],
            'proof' => ['nullable', 'image', 'max:5120'],
        ]);

        try {
            $action->recordPayment($vendorSubmission, $data, $request->file('proof'), $request->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment recorded.');
    }

    public function approve(Request $request, VendorSubmission $vendorSubmission, VendorSubmissionAction $action): RedirectResponse
    {
        $this->authorize('review', $vendorSubmission);
        abort_unless($request->user()->can('vendor-submissions.approve'), 403);

        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:1000']]);

        try {
            $action->approve($vendorSubmission, $request->user(), $data['remarks'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Submission approved — a purchase lead has been created.');
    }

    public function reject(Request $request, VendorSubmission $vendorSubmission, VendorSubmissionAction $action): RedirectResponse
    {
        $this->authorize('review', $vendorSubmission);

        $data = $request->validate(['remarks' => ['required', 'string', 'max:1000']]);

        try {
            $action->reject($vendorSubmission, $request->user(), $data['remarks']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Submission rejected.');
    }
}
