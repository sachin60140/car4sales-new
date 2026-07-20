<?php

namespace App\Domain\Inspections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionMedia extends Model
{
    protected $table = 'inspection_media';

    protected $fillable = [
        'vehicle_inspection_id', 'inspection_item_id', 'type', 'category',
        'file_path', 'thumbnail_path', 'panel_marker', 'captured_at', 'meta', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'panel_marker' => 'array',
            'meta' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(VehicleInspection::class, 'vehicle_inspection_id');
    }
}
