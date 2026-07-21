<?php

namespace App\Domain\VendorSubmissions\Models;

use App\Domain\VendorSubmissions\Enums\VendorProfileStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorProfile extends Model
{
    protected $fillable = [
        'user_id', 'company_name', 'contact_person', 'phone', 'city', 'gst_number',
        'status', 'activated_by', 'activated_at', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorProfileStatus::class,
            'activated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function isActive(): bool
    {
        return $this->status === VendorProfileStatus::Active;
    }
}
