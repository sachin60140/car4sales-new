<?php

namespace App\Domain\SalesLeads\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAssignment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'sales_lead_id', 'role', 'from_user_id', 'to_user_id', 'assigned_by', 'reason',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
