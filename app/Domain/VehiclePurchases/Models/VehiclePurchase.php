<?php

namespace App\Domain\VehiclePurchases\Models;

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Branches\Models\Branch;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Domain\Sellers\Models\Seller;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VehiclePurchase extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'purchase_number', 'purchase_lead_id', 'seller_id', 'branch_id', 'vehicle_id',
        'agreed_price', 'initial_expenses', 'approval_request_id', 'agreement_document_id',
        'purchased_at', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'agreed_price' => 'decimal:2',
            'initial_expenses' => 'decimal:2',
            'purchased_at' => 'datetime',
        ];
    }

    public function purchaseLead(): BelongsTo
    {
        return $this->belongsTo(PurchaseLead::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SellerPayment::class);
    }

    public function possession(): HasOne
    {
        return $this->hasOne(VehiclePossession::class);
    }

    /** Total of approved, non-reversed payments. */
    public function paidAmount(): string
    {
        return (string) $this->payments()
            ->whereIn('status', ['approved', 'paid'])
            ->whereNull('reversal_of')
            ->sum('amount');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'agreed_price', 'vehicle_id'])
            ->logOnlyDirty()
            ->useLogName('vehicle_purchase');
    }
}
