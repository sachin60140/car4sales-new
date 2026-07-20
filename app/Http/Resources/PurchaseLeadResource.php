<?php

namespace App\Http\Resources;

use App\Domain\PurchaseLeads\Models\PurchaseLead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseLead
 */
class PurchaseLeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead_number' => $this->lead_number,
            'seller_name' => $this->seller_name,
            'mobile' => $this->mobile,
            'city' => $this->city,
            'source' => $this->source,
            'registration_number' => $this->registration_number,
            'make' => $this->make,
            'model' => $this->model,
            'variant' => $this->variant,
            'manufacturing_year' => $this->manufacturing_year,
            'fuel_type' => $this->fuel_type,
            'transmission' => $this->transmission,
            'odometer_km' => $this->odometer_km,
            'expected_price' => $this->expected_price,
            'loan_status' => $this->loan_status,
            'priority' => $this->priority,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'next_follow_up_at' => $this->next_follow_up_at?->toIso8601String(),
            'assigned_to' => $this->assigned_to,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee?->only(['id', 'name'])),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch?->only(['id', 'name'])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
