<?php

namespace App\Domain\Inspections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionSection extends Model
{
    protected $fillable = [
        'vehicle_inspection_id', 'key', 'label', 'rating', 'status',
        'remarks', 'repair_estimate', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'repair_estimate' => 'decimal:2',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(VehicleInspection::class, 'vehicle_inspection_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InspectionItem::class);
    }
}
