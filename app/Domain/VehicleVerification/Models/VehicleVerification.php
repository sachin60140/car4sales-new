<?php

namespace App\Domain\VehicleVerification\Models;

use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleVerification extends Model
{
    protected $fillable = [
        'purchase_lead_id', 'type', 'status', 'file_path', 'number',
        'valid_till', 'verified_by', 'verified_at', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'valid_till' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function purchaseLead(): BelongsTo
    {
        return $this->belongsTo(PurchaseLead::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
