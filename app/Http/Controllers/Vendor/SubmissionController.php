<?php

namespace App\Http\Controllers\Vendor;

use App\Domain\Branches\Models\Branch;
use App\Domain\Documents\Services\MediaUploadService;
use App\Domain\VendorSubmissions\Actions\GenerateVendorAgreementAction;
use App\Domain\VendorSubmissions\Actions\VendorSettlementAction;
use App\Domain\VendorSubmissions\Actions\VendorSubmissionAction;
use App\Domain\VendorSubmissions\Enums\ChecklistResult;
use App\Domain\VendorSubmissions\Enums\SubmissionStatus;
use App\Domain\VendorSubmissions\Models\VendorSubmission;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionController extends Controller
{
    public function index(Request $request): Response
    {
        $submissions = VendorSubmission::query()
            ->where('vendor_user_id', $request->user()->id)
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (VendorSubmission $s) => $this->row($s));

        return Inertia::render('vendor/submissions/Index', [
            'submissions' => $submissions,
            'statuses' => SubmissionStatus::options(),
            'filters' => ['status' => $request->string('status')->toString() ?: null],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', VendorSubmission::class);

        return Inertia::render('vendor/submissions/Form', $this->formProps(null));
    }

    public function store(Request $request, VendorSubmissionAction $action): RedirectResponse
    {
        $this->authorize('create', VendorSubmission::class);

        $data = $this->validated($request);
        $submission = $action->save(null, $data, $request->user());

        return redirect()->route('vendor.submissions.edit', $submission)
            ->with('success', 'Draft saved — add photos, then submit for review.');
    }

    public function show(Request $request, VendorSubmission $submission): Response
    {
        $this->authorize('view', $submission);

        return Inertia::render('vendor/submissions/Show', [
            'submission' => $this->detail($submission),
            'docTypes' => collect(VendorSubmission::REQUIRED_KYC_DOCS)->map(fn ($label, $type) => ['type' => $type, 'label' => $label])->values(),
        ]);
    }

    public function edit(Request $request, VendorSubmission $submission): Response|RedirectResponse
    {
        $this->authorize('view', $submission);

        if (! $submission->status->isEditableByVendor()) {
            return redirect()->route('vendor.submissions.show', $submission);
        }

        return Inertia::render('vendor/submissions/Form', $this->formProps($submission));
    }

    public function update(Request $request, VendorSubmission $submission, VendorSubmissionAction $action): RedirectResponse
    {
        $this->authorize('update', $submission);

        $data = $this->validated($request);
        $action->save($submission, $data, $request->user());

        return back()->with('success', 'Submission updated.');
    }

    public function uploadMedia(Request $request, VendorSubmission $submission, MediaUploadService $media): RedirectResponse
    {
        $this->authorize('update', $submission);

        $data = $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['image', 'max:5120'],
            'type' => ['required', 'in:gallery,damage'],
        ]);

        foreach ($request->file('files') as $file) {
            $stored = $media->store($file, "vendor-submissions/{$submission->id}");

            $submission->media()->create([
                'type' => $data['type'],
                'file_path' => $stored['path'],
                'thumbnail_path' => $stored['thumbnail_path'] ?? null,
                'original_name' => $stored['original_name'] ?? null,
                'mime_type' => $stored['mime_type'] ?? null,
                'size_bytes' => $stored['size_bytes'] ?? null,
                'uploaded_by' => $request->user()->id,
            ]);
        }

        $count = count($data['files']);

        return back()->with('success', $count.' '.($count === 1 ? 'image' : 'images').' uploaded.');
    }

    public function deleteMedia(Request $request, \App\Domain\VendorSubmissions\Models\VendorSubmissionMedia $media): RedirectResponse
    {
        $this->authorize('update', $media->submission);

        \Illuminate\Support\Facades\Storage::disk('private')->delete(array_filter([$media->file_path, $media->thumbnail_path]));
        $media->delete();

        return back()->with('success', 'Image removed.');
    }

    public function submit(Request $request, VendorSubmission $submission, VendorSubmissionAction $action): RedirectResponse
    {
        $this->authorize('submit', $submission);

        try {
            $action->submit($submission, $request->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('vendor.submissions.show', $submission)
            ->with('success', 'Submitted for review. We\'ll notify you once it\'s reviewed.');
    }

    public function agreement(Request $request, VendorSubmission $submission, GenerateVendorAgreementAction $action)
    {
        $this->authorize('view', $submission);
        abort_unless($submission->settlement_status->agreementAvailable(), 404);

        return $action->pdf($submission)->download("agreement-{$submission->submission_number}.pdf");
    }

    /**
     * Vendor submits the vehicle owner's details, bank details, and KYC documents
     * (required before the agreement is issued). Owner data stays on the submission.
     */
    public function submitKyc(Request $request, VendorSubmission $submission, VendorSettlementAction $action): RedirectResponse
    {
        abort_unless($submission->vendor_user_id === $request->user()->id, 403);

        $docRules = ['required', 'image', 'max:5120'];
        $data = $request->validate([
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_phone' => ['required', 'string', 'max:20'],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'owner_address' => ['required', 'string', 'max:500'],
            'owner_pan' => ['nullable', 'string', 'max:15'],
            'bank_account_name' => ['required', 'string', 'max:255'],
            'bank_account_number' => ['required', 'string', 'max:30'],
            'bank_ifsc' => ['required', 'string', 'max:15'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            ...array_fill_keys(array_map(fn ($t) => "documents.$t", array_keys(VendorSubmission::REQUIRED_KYC_DOCS)), $docRules),
            'extra_documents' => ['nullable', 'array', 'max:10'],
            'extra_documents.*' => ['image', 'max:5120'],
        ]);

        $owner = $request->only(['owner_name', 'owner_phone', 'owner_email', 'owner_address', 'owner_pan']);
        $bank = $request->only(['bank_account_name', 'bank_account_number', 'bank_ifsc', 'bank_name']);

        try {
            $action->submitOwnerKyc(
                $submission,
                $owner,
                $bank,
                $request->file('documents', []),
                $request->user(),
                $request->file('extra_documents', []),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Owner details & documents submitted for verification.');
    }

    public function requestPayment(Request $request, VendorSubmission $submission, VendorSettlementAction $action): RedirectResponse
    {
        abort_unless($submission->vendor_user_id === $request->user()->id, 403);

        try {
            $action->requestPayment($submission, $request->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment requested. Our team will process it shortly.');
    }

    /** @return array<string, mixed> */
    private function formProps(?VendorSubmission $submission): array
    {
        return [
            'submission' => $submission === null ? null : $this->detail($submission),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'resultOptions' => ChecklistResult::options(),
            'checklistTemplate' => $this->checklistTemplate(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'make' => ['required', 'string', 'max:60'],
            'model' => ['required', 'string', 'max:60'],
            'variant' => ['nullable', 'string', 'max:60'],
            'manufacturing_year' => ['nullable', 'integer', 'min:1990', 'max:'.((int) date('Y') + 1)],
            'registration_number' => ['required', 'string', 'max:20'],
            'registration_state' => ['nullable', 'string', 'max:40'],
            'fuel_type' => ['nullable', 'string', 'max:20'],
            'transmission' => ['nullable', 'string', 'max:20'],
            'color' => ['nullable', 'string', 'max:40'],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'ownership_serial' => ['nullable', 'integer', 'min:1', 'max:10'],
            'expected_amount' => ['required', 'numeric', 'min:0'],
            'overall_remark' => ['nullable', 'string', 'max:1000'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'items' => ['array'],
            'items.*.section' => ['required_with:items', 'string', 'max:60'],
            'items.*.label' => ['required_with:items', 'string', 'max:255'],
            'items.*.result' => ['required_with:items', Rule::enum(ChecklistResult::class)],
            'items.*.rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'items.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function row(VendorSubmission $s): array
    {
        return [
            'id' => $s->id,
            'submission_number' => $s->submission_number,
            'title' => $s->title(),
            'expected_amount' => $s->expected_amount,
            'status' => $s->status->value,
            'status_label' => $s->status->label(),
            'created_at' => $s->created_at->toDateString(),
        ];
    }

    private function detail(VendorSubmission $s): array
    {
        $s->loadMissing(['items', 'media', 'reviewer:id,name', 'purchaseLead:id,lead_number', 'branch:id,name']);
        $cheque = $s->media->firstWhere('type', 'cancelled_cheque');
        $proof = $s->media->firstWhere('type', 'payment_proof');

        return [
            ...$s->only([
                'id', 'submission_number', 'make', 'model', 'variant', 'manufacturing_year',
                'registration_number', 'registration_state', 'fuel_type', 'transmission', 'color',
                'odometer_km', 'ownership_serial', 'expected_amount', 'overall_rating', 'overall_remark',
                'branch_id', 'review_remarks', 'owner_name', 'owner_phone', 'owner_email', 'owner_address',
                'owner_pan', 'kyc_remarks', 'bank_account_name', 'bank_account_number', 'bank_ifsc',
                'bank_name', 'payment_amount', 'payment_mode', 'payment_reference',
            ]),
            'status' => $s->status->value,
            'status_label' => $s->status->label(),
            'settlement_status' => $s->settlement_status->value,
            'settlement_label' => $s->settlement_status->label(),
            'agreement_available' => $s->settlement_status->agreementAvailable(),
            'kyc_submitted_at' => $s->kyc_submitted_at?->toDateString(),
            'payment_date' => $s->payment_date?->toDateString(),
            'editable' => $s->status->isEditableByVendor(),
            'branch' => $s->branch?->only(['id', 'name']),
            'reviewer' => $s->reviewer?->only(['id', 'name']),
            'purchase_lead' => $s->purchaseLead?->only(['id', 'lead_number']),
            'items' => $s->items->map(fn ($i) => [
                'id' => $i->id, 'section' => $i->section, 'label' => $i->label,
                'result' => $i->result->value, 'rating' => $i->rating, 'remarks' => $i->remarks,
            ]),
            'gallery' => $s->media->where('type', 'gallery')->values()->map(fn ($m) => $this->mediaRow($m)),
            'damage' => $s->media->where('type', 'damage')->values()->map(fn ($m) => $this->mediaRow($m)),
            'documents' => $this->documentRows($s),
            'cheque' => $cheque ? $this->mediaRow($cheque) : null,
            'payment_proof' => $proof ? $this->mediaRow($proof) : null,
            'agreement_url' => route('submission-agreement.download', $s),
        ];
    }

    /** Owner-KYC documents grouped by type (+ extras), for review/read-back. */
    private function documentRows(VendorSubmission $s): array
    {
        $docs = [];
        foreach (array_keys(VendorSubmission::REQUIRED_KYC_DOCS) as $type) {
            $m = $s->media->firstWhere('type', $type);
            $docs[$type] = $m ? $this->mediaRow($m) : null;
        }
        $docs['extra'] = $s->media->where('type', 'other_doc')->values()->map(fn ($m) => $this->mediaRow($m));

        return $docs;
    }

    private function mediaRow(\App\Domain\VendorSubmissions\Models\VendorSubmissionMedia $m): array
    {
        return [
            'id' => $m->id,
            'caption' => $m->caption,
            'url' => route('submission-media.view', $m),
        ];
    }

    /** Default condition checklist a vendor fills. @return array<int, array{section: string, label: string}> */
    private function checklistTemplate(): array
    {
        return [
            ['section' => 'Engine', 'label' => 'Engine health / noise'],
            ['section' => 'Engine', 'label' => 'Oil leaks'],
            ['section' => 'Transmission', 'label' => 'Gearbox / clutch'],
            ['section' => 'Brakes', 'label' => 'Brakes & discs'],
            ['section' => 'Suspension', 'label' => 'Suspension & steering'],
            ['section' => 'Electricals', 'label' => 'Lights & electricals'],
            ['section' => 'AC', 'label' => 'Air conditioning'],
            ['section' => 'Tyres', 'label' => 'Tyre condition'],
            ['section' => 'Exterior', 'label' => 'Body & paint'],
            ['section' => 'Interior', 'label' => 'Interior & upholstery'],
            ['section' => 'Documents', 'label' => 'RC / insurance / service history'],
        ];
    }
}
