<?php

namespace App\Domain\Audit\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'login_histories';

    protected $fillable = [
        'user_id', 'email', 'ip_address', 'user_agent', 'device_uuid', 'guard', 'event',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
