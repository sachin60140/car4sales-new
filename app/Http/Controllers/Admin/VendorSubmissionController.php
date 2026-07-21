<?php

namespace App\Http\Controllers\Admin;

use App\Domain\VendorSubmissions\Actions\VendorSubmissionAction;
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
            'items', 'media', 'reviewer:id,name', 'purchaseLead:id,lead_number,status', 'branch:id,name',
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
            ],
            'can' => [
                'review' => $vendorSubmission->status === SubmissionStatus::PendingReview,
            ],
        ]);
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
