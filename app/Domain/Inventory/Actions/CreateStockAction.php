<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates a stock (vehicle) record directly from admin input — the manual entry
 * path for vehicles bought outside the purchase/vendor pipelines. Allocates the
 * next STK sequence, defaults to In Stock, and guards against duplicates by
 * registration number.
 */
class CreateStockAction
{
    public function __construct(private readonly NumberSequenceService $sequences) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?User $actor = null): Vehicle
    {
        return DB::transaction(function () use ($data, $actor) {
            $registration = $data['registration_number'] ?? null;
            if ($registration !== null && $registration !== ''
                && Vehicle::query()->where('registration_number', $registration)->exists()) {
                throw ValidationException::withMessages([
                    'registration_number' => 'A vehicle with this registration number already exists.',
                ]);
            }

            $status = $data['status'] ?? VehicleStatus::InStock->value;
            $title = trim(($data['make'] ?? '').' '.($data['model'] ?? '').' '.($data['variant'] ?? ''));

            $vehicle = new Vehicle([
                ...$data,
                'stock_number' => $this->sequences->next('stock'),
                'status' => $status,
                'title' => ($data['title'] ?? null) ?: $title,
                'created_by' => $actor?->id,
            ]);

            $vehicle->slug = Str::slug($title.'-'.$vehicle->stock_number);
            $vehicle->save();

            $vehicle->writeStatusHistory(null, $status, $actor, 'Manual stock entry.');

            return $vehicle;
        });
    }
}
