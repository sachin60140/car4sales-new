<?php

namespace App\Domain\RolesPermissions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalLimit extends Model
{
    protected $fillable = [
        'role_id', 'module', 'max_amount', 'requires_escalation',
    ];

    protected function casts(): array
    {
        return [
            'max_amount' => 'decimal:2',
            'requires_escalation' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
