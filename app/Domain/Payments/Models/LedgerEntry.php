<?php

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\LedgerHead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $fillable = [
        'customer_ledger_id', 'type', 'head', 'amount', 'reference_type',
        'reference_id', 'reversal_of', 'posted_by', 'posted_at', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'head' => LedgerHead::class,
            'amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(CustomerLedger::class, 'customer_ledger_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isReversal(): bool
    {
        return $this->reversal_of !== null;
    }
}
