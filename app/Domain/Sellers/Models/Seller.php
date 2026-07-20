<?php

namespace App\Domain\Sellers\Models;

use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Seller extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'seller_code', 'type', 'name', 'mobile', 'alt_mobile', 'email',
        'address', 'city', 'state', 'pin_code', 'gst_number', 'pan_number',
        'bank_account_name', 'bank_account_number', 'bank_ifsc', 'bank_name',
        'is_blacklisted', 'remarks', 'created_by',
    ];

    protected $hidden = ['bank_account_number'];

    protected function casts(): array
    {
        return [
            'is_blacklisted' => 'boolean',
            'bank_account_number' => 'encrypted',
        ];
    }

    public function purchaseLeads(): HasMany
    {
        return $this->hasMany(PurchaseLead::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SellerDocument::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'mobile', 'type', 'is_blacklisted', 'bank_ifsc'])
            ->logOnlyDirty()
            ->useLogName('seller');
    }
}
