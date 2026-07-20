<?php

namespace App\Domain\SalesLeads\Models;

use Illuminate\Database\Eloquent\Model;

class LeadLostReason extends Model
{
    protected $fillable = [
        'label', 'category', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
