<?php

namespace App\Domain\SalesLeads\Models;

use App\Domain\SalesLeads\Enums\CallOutcome;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadFollowup extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'sales_lead_id', 'user_id', 'channel', 'call_outcome', 'remarks',
        'next_follow_up_at', 'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'call_outcome' => CallOutcome::class,
            'next_follow_up_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
