<?php

namespace App\Domain\RolesPermissions\Models;

use App\Domain\RolesPermissions\Enums\DataScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleMeta extends Model
{
    protected $table = 'role_meta';

    protected $fillable = [
        'role_id', 'data_scope', 'scope_branch_ids', 'description', 'is_system',
    ];

    protected function casts(): array
    {
        return [
            'data_scope' => DataScope::class,
            'scope_branch_ids' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
