<?php

namespace App\Domain\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['finance_application_id', 'from_status', 'to_status', 'changed_by', 'remarks'];

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
