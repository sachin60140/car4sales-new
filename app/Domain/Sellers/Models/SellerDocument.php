<?php

namespace App\Domain\Sellers\Models;

use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerDocument extends Model
{
    protected $fillable = [
        'seller_id', 'purchase_lead_id', 'type', 'file_path', 'original_name',
        'mime_type', 'size_bytes', 'status', 'verified_by', 'verified_at',
        'rejection_reason', 'meta', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function purchaseLead(): BelongsTo
    {
        return $this->belongsTo(PurchaseLead::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
