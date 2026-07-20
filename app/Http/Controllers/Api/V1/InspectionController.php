<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Documents\Services\MediaUploadService;
use App\Domain\Inspections\Actions\SubmitInspectionAction;
use App\Domain\Inspections\Models\VehicleInspection;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InspectionController extends Controller
{
    /**
     * Inspections assigned to the authenticated inspector.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VehicleInspection::class);

        $inspections = VehicleInspection::query()
            ->with(['purchaseLead:id,lead_number,make,model,registration_number'])
            ->when(! $request->user()->hasAnyRole(['Super Admin', 'Purchase Manager', 'Branch Manager']),
                fn ($q) => $q->where('inspector_id', $request->user()->id))
            ->when($request->query('filter.status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return ApiResponse::paginated($inspections->through(fn (VehicleInspection $i) => [
            'id' => $i->id,
            'inspection_number' => $i->inspection_number,
            'status' => $i->status->value,
            'result' => $i->result,
            'overall_grade' => $i->overall_grade,
            'scheduled_at' => $i->scheduled_at?->toIso8601String(),
            'lead' => $i->purchaseLead?->only(['id', 'lead_number', 'make', 'model', 'registration_number']),
        ]));
    }

    public function show(Request $request, VehicleInspection $inspection): JsonResponse
    {
        $this->authorize('view', $inspection);

        $inspection->load(['sections.items', 'media']);

        return ApiResponse::success([
            'id' => $inspection->id,
            'inspection_number' => $inspection->inspection_number,
            'status' => $inspection->status->value,
            'locked' => $inspection->isLocked(),
            'odometer_km' => $inspection->odometer_km,
            'overall_grade' => $inspection->overall_grade,
            'total_repair_estimate' => $inspection->total_repair_estimate,
            'sections' => $inspection->sections->map(fn ($s) => [
                'id' => $s->id,
                'key' => $s->key,
                'label' => $s->label,
                'rating' => $s->rating,
                'status' => $s->status,
                'repair_estimate' => $s->repair_estimate,
                'remarks' => $s->remarks,
                'items' => $s->items->map(fn ($it) => [
                    'id' => $it->id, 'label' => $it->label, 'value' => $it->value, 'severity' => $it->severity,
                ]),
            ]),
            'media_count' => $inspection->media->count(),
        ]);
    }

    public function update(Request $request, VehicleInspection $inspection): JsonResponse
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
                'status' => 'in_progress',
                'started_at' => $inspection->started_at ?? now(),
            ]);

            foreach ($data['sections'] ?? [] as $s) {
                $inspection->sections()->where('id', $s['id'])->update([
                    'rating' => $s['rating'] ?? null,
                    'status' => $s['status'] ?? 'na',
                    'remarks' => $s['remarks'] ?? null,
                    'repair_estimate' => $s['repair_estimate'] ?? 0,
                ]);
            }
        });

        return ApiResponse::success(null, 'Inspection saved.');
    }

    public function submit(Request $request, VehicleInspection $inspection, SubmitInspectionAction $action): JsonResponse
    {
        $this->authorize('update', $inspection);

        $data = $request->validate([
            'result' => ['required', 'string', 'in:recommended,recommended_with_repairs,management_approval,not_recommended'],
        ]);

        $action->execute($inspection, $request->user(), $data['result']);

        return ApiResponse::success(null, 'Inspection submitted and locked.');
    }

    public function uploadMedia(Request $request, VehicleInspection $inspection, MediaUploadService $media): JsonResponse
    {
        $this->authorize('update', $inspection);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,mp4,mov'],
            'category' => ['nullable', 'string', 'max:40'],
            'inspection_item_id' => ['nullable', 'integer'],
        ]);

        $stored = $media->store($data['file'], "inspections/{$inspection->id}");
        $isVideo = str_starts_with($data['file']->getClientMimeType(), 'video/');

        $mediaRow = $inspection->media()->create([
            'inspection_item_id' => $data['inspection_item_id'] ?? null,
            'type' => $isVideo ? 'video' : 'photo',
            'category' => $data['category'] ?? null,
            'file_path' => $stored['path'],
            'thumbnail_path' => $stored['thumbnail_path'],
            'captured_at' => now(),
            'uploaded_by' => $request->user()->id,
        ]);

        return ApiResponse::success(['id' => $mediaRow->id], 'Media uploaded.', status: 201);
    }
}
