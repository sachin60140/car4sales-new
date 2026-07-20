<?php

namespace App\Domain\PublicWebsite\Support;

use App\Domain\Documents\Services\MediaUploadService;
use App\Domain\Inventory\Models\Vehicle;

/**
 * Shapes a stock vehicle into the ONLY data allowed on the public website.
 *
 * Never exposes: chassis/engine number, purchase price, landed cost, minimum
 * selling price, internal remarks, expenses, or approval history (spec §6).
 */
class PublicVehiclePresenter
{
    public function __construct(private readonly MediaUploadService $media) {}

    /** Compact card shape for listings. */
    public function card(Vehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'slug' => $vehicle->slug,
            'title' => $this->title($vehicle),
            'make' => $vehicle->make,
            'model' => $vehicle->model,
            'variant' => $vehicle->variant,
            'manufacturing_year' => $vehicle->manufacturing_year,
            'fuel_type' => $vehicle->fuel_type,
            'transmission' => $vehicle->transmission,
            'odometer_km' => $vehicle->odometer_km,
            'ownership_serial' => $vehicle->ownership_serial,
            'color' => $vehicle->color,
            'body_type' => $vehicle->body_type,
            'asking_price' => $vehicle->asking_price,
            'branch' => $vehicle->relationLoaded('branch') ? $vehicle->branch?->only(['name', 'city']) : null,
            'availability' => $vehicle->availability(),
            'is_featured' => $vehicle->is_featured,
            'thumbnail' => $this->thumbnail($vehicle),
        ];
    }

    /** Full detail shape for the vehicle page. */
    public function detail(Vehicle $vehicle): array
    {
        return [
            ...$this->card($vehicle),
            'registration_year' => $vehicle->registration_year,
            'registration_state' => $vehicle->registration_state,
            'insurance_status' => $vehicle->insurance_status,
            'inspection_grade' => $vehicle->inspection_grade,
            'description' => $vehicle->description,
            'key_features' => $vehicle->key_features ?? [],
            'branch' => $vehicle->branch?->only(['name', 'city', 'phone', 'address']),
            'gallery' => $this->gallery($vehicle),
        ];
    }

    private function title(Vehicle $vehicle): string
    {
        return $vehicle->title ?: trim($vehicle->make.' '.$vehicle->model.' '.($vehicle->variant ?? ''));
    }

    private function thumbnail(Vehicle $vehicle): ?string
    {
        $media = $vehicle->relationLoaded('publicMedia') ? $vehicle->publicMedia : $vehicle->publicMedia()->get();
        $first = $media->firstWhere('type', 'photo') ?? $media->first();

        return $first ? $this->media->signedUrl($first->thumbnail_path ?? $first->file_path, 60) : null;
    }

    /** @return array<int, array{type: string, url: string}> */
    private function gallery(Vehicle $vehicle): array
    {
        return $vehicle->publicMedia()->get()
            ->map(fn ($m) => [
                'type' => $m->type,
                'url' => $this->media->signedUrl($m->file_path, 60),
            ])
            ->all();
    }
}
