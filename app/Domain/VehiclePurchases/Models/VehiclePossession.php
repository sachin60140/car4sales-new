<?php

namespace App\Domain\VehiclePurchases\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehiclePossession extends Model
{
    protected $fillable = [
        'vehicle_purchase_id', 'vehicle_received', 'original_rc_received', 'insurance_received',
        'puc_received', 'noc_received', 'form_35_received', 'main_key', 'spare_key',
        'service_book', 'tool_kit', 'spare_wheel', 'accessories', 'odometer_km', 'fuel_level',
        'seller_signature_path', 'employee_signature_path', 'possessed_at', 'remarks', 'received_by',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_received' => 'boolean',
            'original_rc_received' => 'boolean',
            'insurance_received' => 'boolean',
            'puc_received' => 'boolean',
            'noc_received' => 'boolean',
            'form_35_received' => 'boolean',
            'main_key' => 'boolean',
            'spare_key' => 'boolean',
            'service_book' => 'boolean',
            'tool_kit' => 'boolean',
            'spare_wheel' => 'boolean',
            'accessories' => 'boolean',
            'possessed_at' => 'datetime',
        ];
    }

    public function vehiclePurchase(): BelongsTo
    {
        return $this->belongsTo(VehiclePurchase::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** The mandatory possession item — the vehicle must physically be received. */
    public function isComplete(): bool
    {
        return $this->vehicle_received && $this->possessed_at !== null;
    }
}
