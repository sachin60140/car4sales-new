<?php

namespace App\Domain\RTO\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RtoDocumentMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rto_case_id', 'document', 'from_holder', 'to_holder', 'moved_by', 'moved_at', 'remarks', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'moved_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function rtoCase(): BelongsTo
    {
        return $this->belongsTo(RtoCase::class);
    }

    public function mover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }
}
