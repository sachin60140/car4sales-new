<?php

namespace App\Domain\RTO\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RtoExpense extends Model
{
    protected $fillable = [
        'rto_case_id', 'head', 'amount', 'reference', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function rtoCase(): BelongsTo
    {
        return $this->belongsTo(RtoCase::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
