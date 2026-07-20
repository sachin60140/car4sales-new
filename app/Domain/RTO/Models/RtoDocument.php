<?php

namespace App\Domain\RTO\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RtoDocument extends Model
{
    protected $fillable = [
        'rto_case_id', 'type', 'file_path', 'status', 'uploaded_by',
    ];

    public function rtoCase(): BelongsTo
    {
        return $this->belongsTo(RtoCase::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
