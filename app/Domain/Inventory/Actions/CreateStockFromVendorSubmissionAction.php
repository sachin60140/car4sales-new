<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\VendorSubmissions\Models\VendorSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates the stock (vehicle) record directly from a paid vendor submission —
 * the vendor-purchase path has no VehiclePurchase, so the vehicle is built from
 * the submission's details. Landed cost = the amount actually paid to the owner.
 * Guards against duplicates by registration number.
 */
class CreateStockFromVendorSubmissionAction
{
    public function __construct(private readonly NumberSequenceService $sequences) {}

    public function execute(VendorSubmission $submission, ?User $actor = null, ?int $odometerKm = null): Vehicle
    {
        return DB::transaction(function () use ($submission, $actor, $odometerKm) {
            if ($submission->vehicle_id !== null) {
                throw new RuntimeException('Stock already exists for this submission.');
            }

            $this->assertNotDuplicate($submission->registration_number);

            $price = (float) ($submission->payment_amount ?? $submission->expected_amount);
            $title = trim(($submission->make ?? '').' '.($submission->model ?? '').' '.($submission->variant ?? ''));

            // Auto-fill the acquisition/source panel from the vendor-partner deal:
            // the car was bought (via the vendor partner) from the registered owner,
            // who received the payout. Keep the partner traceable in the reference.
            $partnerName = $submission->vendor?->name;
            $reference = $submission->submission_number;
            if ($partnerName) {
                $reference = Str::limit($reference.' · Partner: '.$partnerName, 100, '');
            }

            $vehicle = new Vehicle([
                'stock_number' => $this->sequences->next('stock'),
                'registration_number' => $submission->registration_number,
                'registration_state' => $submission->registration_state,
                'chassis_number' => $submission->chassis_number,
                'make' => $submission->make ?: 'Unknown',
                'model' => $submission->model ?: 'Unknown',
                'variant' => $submission->variant,
                'manufacturing_year' => $submission->manufacturing_year,
                'fuel_type' => $submission->fuel_type,
                'transmission' => $submission->transmission,
                'color' => $submission->color,
                'odometer_km' => $odometerKm ?? $submission->odometer_km,
                'ownership_serial' => $submission->ownership_serial,
                'purchase_price' => $price,
                'landed_cost' => $price,
                'branch_id' => $submission->branch_id,
                'status' => VehicleStatus::InStock->value,
                'title' => $title,
                'created_by' => $actor?->id,
                'acquisition_source' => 'vendor',
                'seller_name' => $submission->owner_name ?: $partnerName,
                'seller_contact' => $submission->owner_phone,
                'purchased_by' => $submission->paid_by ?? $actor?->id,
                'purchased_at' => $submission->payment_date ?? $submission->paid_at ?? now(),
                'purchase_reference' => $reference,
            ]);

            $vehicle->slug = Str::slug($title.'-'.$vehicle->stock_number);
            $vehicle->save();

            $vehicle->writeStatusHistory(null, VehicleStatus::InStock->value, $actor, 'Auto stock entry from vendor submission '.$submission->submission_number);

            // Carry the owner-KYC documents onto the vehicle so they show in the
            // inventory Documents tab (same private files, no duplication).
            $verifications = $submission->document_verifications ?? [];
            foreach ($submission->documentMedia()->get() as $media) {
                $key = preg_replace('/_(front|back)$/', '', $media->type);
                $verification = $verifications[$key] ?? [];

                $vehicle->documents()->create([
                    'type' => $media->type,
                    'file_path' => $media->file_path,
                    'number' => $verification['number'] ?? null,
                    'valid_till' => $verification['valid_till'] ?? null,
                    'status' => ($verification['status'] ?? null) === 'verified' ? 'verified' : 'received',
                    'uploaded_by' => $actor?->id,
                ]);
            }

            return $vehicle;
        });
    }

    private function assertNotDuplicate(?string $registration): void
    {
        if ($registration !== null && $registration !== ''
            && Vehicle::query()->where('registration_number', $registration)->exists()) {
            throw new RuntimeException('A stock vehicle already exists with the same registration number.');
        }
    }
}
