<?php

namespace App\Domain\RTO\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RtoStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rto_case_id', 'from_status', 'to_status', 'changed_by', 'remarks', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function rtoCase(): BelongsTo
    {
        return $this->belongsTo(RtoCase::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
