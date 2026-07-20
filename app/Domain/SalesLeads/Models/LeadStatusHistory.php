<?php

namespace App\Domain\SalesLeads\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'sales_lead_id', 'from_status', 'to_status', 'changed_by', 'remarks',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
