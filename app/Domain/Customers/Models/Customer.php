<?php

namespace App\Domain\Customers\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\SalesLeads\Models\SalesLead;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Customer extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'customer_code', 'name', 'mobile', 'alt_mobile', 'email', 'address',
        'city', 'state', 'pin_code', 'occupation', 'dob', 'aadhaar_number', 'pan_number',
        'kyc_status', 'branch_id', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'meta' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class);
    }

    public function salesLeads(): HasMany
    {
        return $this->hasMany(SalesLead::class)->latest();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('customer');
    }
}
