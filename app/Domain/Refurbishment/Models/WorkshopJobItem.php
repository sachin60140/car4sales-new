<?php

namespace App\Domain\Refurbishment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopJobItem extends Model
{
    protected $fillable = [
        'workshop_job_id', 'defect', 'work_type', 'description',
        'estimate', 'approved_amount', 'actual_amount', 'status',
    ];

    protected function casts(): array
    {
        return [
            'estimate' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(WorkshopJob::class, 'workshop_job_id');
    }
}
