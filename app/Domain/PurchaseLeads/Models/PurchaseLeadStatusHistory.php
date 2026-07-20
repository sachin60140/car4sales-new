<?php

namespace App\Domain\PurchaseLeads\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseLeadStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'purchase_lead_id', 'from_status', 'to_status', 'changed_by', 'remarks',
    ];

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
