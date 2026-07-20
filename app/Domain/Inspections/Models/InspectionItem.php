<?php

namespace App\Domain\Inspections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionItem extends Model
{
    protected $fillable = [
        'inspection_section_id', 'checklist_item_id', 'label', 'value',
        'severity', 'remarks', 'repair_estimate',
    ];

    protected function casts(): array
    {
        return [
            'repair_estimate' => 'decimal:2',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(InspectionSection::class, 'inspection_section_id');
    }
}
