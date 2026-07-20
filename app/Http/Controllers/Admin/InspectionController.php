<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Documents\Services\MediaUploadService;
use App\Domain\Inspections\Actions\SubmitInspectionAction;
use App\Domain\Inspections\Enums\InspectionStatus;
use App\Domain\Inspections\Models\InspectionChecklistItem;
use App\Domain\Inspections\Models\VehicleInspection;
use App\Domain\PurchaseLeads\Enums\PurchaseLeadStatus;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Http\Controllers\Controller;
use App\Support\Workflow\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InspectionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', VehicleInspection::class);

        $inspections = VehicleInspection::query()
            ->with(['purchaseLead:id,lead_number,make,model,registration_number', 'inspector:id,name', 'branch:id,name'])
            ->when($request->user()->hasRole('Inspector') && ! $request->user()->hasAnyRole(['Super Admin', 'Purchase Manager', 'Branch Manager']),
                fn ($q) => $q->where('inspector_id', $request->user()->id))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/purchase/inspections/Index', [
            'inspections' => $inspections,
            'filters' => ['status' => $request->string('status')->toString() ?: null],
        ]);
    }

    public function store(Request $request, NumberSequenceService $sequences, WorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('inspections.create'), 403);

        $data = $request->validate([
            'purchase_lead_id' => ['required', 'integer', 'exists:purchase_leads,id'],
            'inspector_id' => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_at' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $lead = PurchaseLead::query()->findOrFail($data['purchase_lead_id']);

        $inspection = DB::transaction(function () use ($data, $lead, $sequences, $request, $workflow) {
            $inspection = VehicleInspection::query()->create([
                'inspection_number' => $sequences->next('inspection'),
                'purchase_lead_id' => $lead->id,
                'inspector_id' => $data['inspector_id'] ?? $request->user()->id,
                'branch_id' => $lead->branch_id,
                'scheduled_at' => $data['scheduled_at'] ?? now(),
                'location' => $data['location'] ?? $lead->inspection_location,
                'status' => InspectionStatus::Scheduled->value,
            ]);

            // Pre-build sections from the active checklist.
            $items = InspectionChecklistItem::query()->where('is_active', true)->orderBy('sort_order')->get()->groupBy('section_key');
            $order = 0;

            foreach ($items as $sectionKey => $sectionItems) {
                $section = $inspection->sections()->create([
                    'key' => $sectionKey,
                    'label' => str($sectionKey)->replace('_', ' ')->title(),
                    'status' => 'na',
                    'sort_order' => $order++,
                ]);

                foreach ($sectionItems as $item) {
                    $section->items()->create([
                        'checklist_item_id' => $item->id,
                        'label' => $item->label,
                        'value' => 'na',
                        'severity' => $item->is_critical ? 'critical' : null,
                    ]);
                }
            }

            if ($lead->status === PurchaseLeadStatus::Contacted || $lead->status === PurchaseLeadStatus::New) {
                $workflow->transition($lead, PurchaseLeadStatus::InspectionScheduled, $request->user(), 'Inspection scheduled', force: true);
            }

            return $inspection;
        });

        return redirect()
            ->route('admin.inspections.show', $inspection)
            ->with('success', "Inspection {$inspection->inspection_number} created.");
    }

    public function show(Request $request, VehicleInspection $inspection): Response
    {
        $this->authorize('view', $inspection);

        $inspection->load([
            'purchaseLead:id,lead_number,make,model,variant,registration_number,manufacturing_year',
            'inspector:id,name',
            'sections.items',
            'media',
        ]);

        return Inertia::render('admin/purchase/inspections/Show', [
            'inspection' => $inspection,
            'can' => [
                'edit' => $request->user()->can('update', $inspection),
                'review' => $request->user()->can('review', $inspection),
            ],
        ]);
    }

    public function update(Request $request, VehicleInspection $inspection): RedirectResponse
    {
        $this->authorize('update', $inspection);

        $data = $request->validate([
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'overall_grade' => ['nullable', 'string', 'in:A,B,C,D'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'sections' => ['array'],
            'sections.*.id' => ['required', 'integer'],
            'sections.*.rating' => ['nullable', 'integer', 'between:1,5'],
            'sections.*.status' => ['nullable', 'string', 'in:pass,fail,na'],
            'sections.*.remarks' => ['nullable', 'string', 'max:500'],
            'sections.*.repair_estimate' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $inspection) {
            $inspection->update([
                'odometer_km' => $data['odometer_km'] ?? $inspection->odometer_km,
                'overall_grade' => $data['overall_grade'] ?? $inspection->overall_grade,
                'remarks' => $data['remarks'] ?? $inspection->remarks,
                'status' => InspectionStatus::InProgress->value,
                'started_at' => $inspection->started_at ?? now(),
            ]);

            foreach ($data['sections'] ?? [] as $sectionData) {
                $inspection->sections()->where('id', $sectionData['id'])->update([
                    'rating' => $sectionData['rating'] ?? null,
                    'status' => $sectionData['status'] ?? 'na',
                    'remarks' => $sectionData['remarks'] ?? null,
                    'repair_estimate' => $sectionData['repair_estimate'] ?? 0,
                ]);
            }
        });

        return back()->with('success', 'Inspection saved.');
    }

    public function submit(Request $request, VehicleInspection $inspection, SubmitInspectionAction $action): RedirectResponse
    {
        $this->authorize('update', $inspection);

        $data = $request->validate([
            'result' => ['required', 'string', 'in:recommended,recommended_with_repairs,management_approval,not_recommended'],
        ]);

        $action->execute($inspection, $request->user(), $data['result']);

        return redirect()
            ->route('admin.inspections.show', $inspection)
            ->with('success', 'Inspection submitted and locked.');
    }

    public function uploadMedia(Request $request, VehicleInspection $inspection, MediaUploadService $media): RedirectResponse
    {
        $this->authorize('update', $inspection);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,mp4,mov'],
            'category' => ['nullable', 'string', 'max:40'],
            'inspection_item_id' => ['nullable', 'integer'],
        ]);

        $stored = $media->store($data['file'], "inspections/{$inspection->id}");
        $isVideo = str_starts_with($data['file']->getClientMimeType(), 'video/');

        $inspection->media()->create([
            'inspection_item_id' => $data['inspection_item_id'] ?? null,
            'type' => $isVideo ? 'video' : 'photo',
            'category' => $data['category'] ?? null,
            'file_path' => $stored['path'],
            'thumbnail_path' => $stored['thumbnail_path'],
            'captured_at' => now(),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Media uploaded.');
    }
}
