<?php

namespace App\Domain\VendorSubmissions\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPartnerDocument extends Model
{
    protected $fillable = [
        'vendor_profile_id', 'type', 'file_path', 'thumbnail_path', 'original_name',
        'mime_type', 'size_bytes', 'number', 'status', 'remarks',
        'uploaded_by', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_profile_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
