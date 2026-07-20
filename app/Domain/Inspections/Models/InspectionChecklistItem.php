<?php

namespace App\Domain\Inspections\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionChecklistItem extends Model
{
    protected $fillable = [
        'section_key', 'label', 'is_critical', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_critical' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
