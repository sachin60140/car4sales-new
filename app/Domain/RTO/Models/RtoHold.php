<?php

namespace App\Domain\RTO\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RtoHold extends Model
{
    protected $fillable = [
        'rto_case_id', 'amount', 'reason', 'status', 'held_by', 'released_by', 'released_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'released_at' => 'datetime',
        ];
    }

    public function rtoCase(): BelongsTo
    {
        return $this->belongsTo(RtoCase::class);
    }

    public function heldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
