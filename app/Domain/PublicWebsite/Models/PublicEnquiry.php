<?php

namespace App\Domain\PublicWebsite\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\PublicWebsite\Enums\EnquiryType;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PublicEnquiry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'enquiry_number', 'type', 'name', 'mobile', 'email', 'city', 'vehicle_id',
        'message', 'consent', 'otp_verified_at', 'source', 'campaign', 'utm',
        'ip_address', 'user_agent', 'branch_id', 'sales_lead_id', 'purchase_lead_id',
        'assigned_to', 'status', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'type' => EnquiryType::class,
            'consent' => 'boolean',
            'otp_verified_at' => 'datetime',
            'utm' => 'array',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchaseLead(): BelongsTo
    {
        return $this->belongsTo(PurchaseLead::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
