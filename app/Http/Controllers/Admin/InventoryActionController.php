<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Branches\Models\Branch;
use App\Domain\Documents\Services\MediaUploadService;
use App\Domain\Inventory\Actions\PublishVehicleAction;
use App\Domain\Inventory\Actions\RecordVehicleMovementAction;
use App\Domain\Inventory\Actions\TransferVehicleAction;
use App\Domain\Inventory\Actions\UpdateVehiclePriceAction;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Inventory\Models\VehicleExpense;
use App\Domain\Inventory\Models\VehicleMedia;
use App\Domain\Inventory\Services\VehicleExpenseService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InventoryActionController extends Controller
{
    public function uploadMedia(Request $request, Vehicle $vehicle, MediaUploadService $media): RedirectResponse
    {
        $this->authorize('update', $vehicle);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,mp4,mov'],
            'category' => ['nullable', 'string', 'max:40'],
            'is_public' => ['boolean'],
        ]);

        $stored = $media->store($data['file'], "vehicles/{$vehicle->id}");
        $isVideo = str_starts_with($data['file']->getClientMimeType(), 'video/');

        $vehicle->media()->create([
            'type' => $isVideo ? 'video' : 'photo',
            'category' => $data['category'] ?? null,
            'file_path' => $stored['path'],
            'thumbnail_path' => $stored['thumbnail_path'],
            'is_primary' => $vehicle->media()->count() === 0,
            'is_public' => $data['is_public'] ?? false,
            'sort_order' => $vehicle->media()->count(),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Media uploaded.');
    }

    public function deleteMedia(Request $request, Vehicle $vehicle, VehicleMedia $media): RedirectResponse
    {
        $this->authorize('update', $vehicle);
        abort_unless($media->vehicle_id === $vehicle->id, 404);

        $media->delete();

        return back()->with('success', 'Media removed.');
    }

    public function uploadDocument(Request $request, Vehicle $vehicle, MediaUploadService $media): RedirectResponse
    {
        $this->authorize('update', $vehicle);

        $data = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
            'number' => ['nullable', 'string', 'max:255'],
            'valid_till' => ['nullable', 'date'],
        ]);

        $stored = $media->store($data['file'], "vehicles/{$vehicle->id}/documents");

        $vehicle->documents()->create([
            'type' => $data['type'],
            'file_path' => $stored['path'],
            'number' => $data['number'] ?? null,
            'valid_till' => $data['valid_till'] ?? null,
            'status' => 'received',
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function addExpense(Request $request, Vehicle $vehicle, VehicleExpenseService $service): RedirectResponse
    {
        abort_unless($request->user()->can('refurbishment.create') || $request->user()->can('vehicles.update'), 403);

        $data = $request->validate([
            'category' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
        ]);

        $service->create($vehicle, $data, $request->user());

        return back()->with('success', 'Expense recorded (pending approval).');
    }

    public function approveExpense(Request $request, VehicleExpense $expense, VehicleExpenseService $service): RedirectResponse
    {
        abort_unless($request->user()->can('refurbishment.approve'), 403);

        $service->approve($expense, $request->user());

        return back()->with('success', 'Expense approved and added to landed cost.');
    }

    public function reverseExpense(Request $request, VehicleExpense $expense, VehicleExpenseService $service): RedirectResponse
    {
        abort_unless($request->user()->can('payments.reverse-payment') || $request->user()->hasRole('Accounts Manager'), 403);

        $data = $request->validate(['remarks' => ['required', 'string', 'max:500']]);
        $service->reverse($expense, $request->user(), $data['remarks']);

        return back()->with('success', 'Expense reversed.');
    }

    public function transfer(Request $request, Vehicle $vehicle, TransferVehicleAction $action): RedirectResponse
    {
        $this->authorize('update', $vehicle);

        $data = $request->validate([
            'to_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'parking_location' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $action->execute($vehicle, Branch::query()->findOrFail($data['to_branch_id']), $request->user(), $data['parking_location'] ?? null, $data['remarks'] ?? null);

        return back()->with('success', 'Vehicle transferred.');
    }

    public function move(Request $request, Vehicle $vehicle, RecordVehicleMovementAction $action): RedirectResponse
    {
        $this->authorize('update', $vehicle);

        $data = $request->validate([
            'type' => ['required', 'string'],
            'to_location' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'expected_return_at' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $action->out($vehicle, MovementType::from($data['type']), $data, $request->user());

        return back()->with('success', 'Movement recorded.');
    }

    public function updatePrice(Request $request, Vehicle $vehicle, UpdateVehiclePriceAction $action): RedirectResponse
    {
        $this->authorize('update', $vehicle);

        $data = $request->validate([
            'price_type' => ['required', 'string', 'in:asking,minimum'],
            'new_price' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $action->execute($vehicle, $data['price_type'], (float) $data['new_price'], $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Price updated.');
    }

    public function publish(Request $request, Vehicle $vehicle, PublishVehicleAction $action): RedirectResponse
    {
        $this->authorize('update', $vehicle);

        $data = $request->validate([
            'web' => ['boolean'],
            'mobile' => ['boolean'],
        ]);

        $action->publish($vehicle, $request->user(), $data['web'] ?? true, $data['mobile'] ?? true);

        return back()->with('success', 'Vehicle published.');
    }

    public function unpublish(Request $request, Vehicle $vehicle, PublishVehicleAction $action): RedirectResponse
    {
        $this->authorize('update', $vehicle);

        $action->unpublish($vehicle, $request->user());

        return back()->with('success', 'Vehicle unpublished.');
    }
}
