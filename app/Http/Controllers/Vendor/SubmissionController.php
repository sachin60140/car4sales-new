<?php

namespace App\Http\Controllers\Vendor;

use App\Domain\Branches\Models\Branch;
use App\Domain\Documents\Services\MediaUploadService;
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
            'registration_number' => ['nullable', 'string', 'max:20'],
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

        return [
            ...$s->only([
                'id', 'submission_number', 'make', 'model', 'variant', 'manufacturing_year',
                'registration_number', 'registration_state', 'fuel_type', 'transmission', 'color',
                'odometer_km', 'ownership_serial', 'expected_amount', 'overall_rating', 'overall_remark',
                'branch_id', 'review_remarks',
            ]),
            'status' => $s->status->value,
            'status_label' => $s->status->label(),
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
        ];
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
