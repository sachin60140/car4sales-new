<?php

namespace App\Domain\PurchaseLeads\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\Inspections\Models\VehicleInspection;
use App\Domain\PurchaseLeads\Enums\PurchaseLeadStatus;
use App\Domain\Sellers\Models\Seller;
use App\Domain\Sellers\Models\SellerDocument;
use App\Domain\Valuations\Models\VehicleValuation;
use App\Domain\VehiclePurchases\Models\VehiclePurchase;
use App\Domain\VehicleVerification\Models\VehicleVerification;
use App\Models\User;
use App\Support\Workflow\HasTransitions;
use App\Support\Workflow\RecordsStatusHistory;
use App\Support\Workflow\Transitionable;
use Database\Factories\PurchaseLeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PurchaseLead extends Model implements Transitionable
{
    /** @use HasFactory<PurchaseLeadFactory> */
    use HasFactory, LogsActivity, RecordsStatusHistory, SoftDeletes;

    protected static function newFactory(): PurchaseLeadFactory
    {
        return PurchaseLeadFactory::new();
    }

    protected string $statusEnum = PurchaseLeadStatus::class;

    protected $fillable = [
        'lead_number', 'seller_id', 'seller_name', 'seller_type', 'mobile', 'alt_mobile',
        'email', 'address', 'city', 'pin_code', 'source', 'registration_number',
        'make', 'model', 'variant', 'manufacturing_year', 'fuel_type', 'transmission',
        'odometer_km', 'expected_price', 'loan_status', 'inspection_location',
        'assigned_to', 'branch_id', 'priority', 'next_follow_up_at', 'status',
        'lost_reason', 'remarks', 'utm', 'meta', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseLeadStatus::class,
            'next_follow_up_at' => 'datetime',
            'expected_price' => 'decimal:2',
            'utm' => 'array',
            'meta' => 'array',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function followups(): HasMany
    {
        return $this->hasMany(PurchaseFollowup::class)->latest();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(PurchaseLeadStatusHistory::class)->latest();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SellerDocument::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(VehicleVerification::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(VehicleInspection::class);
    }

    public function latestInspection(): HasOne
    {
        return $this->hasOne(VehicleInspection::class)->latestOfMany();
    }

    public function valuation(): HasOne
    {
        return $this->hasOne(VehicleValuation::class)->latestOfMany();
    }

    public function purchase(): HasOne
    {
        return $this->hasOne(VehiclePurchase::class)->latestOfMany();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'assigned_to', 'priority', 'expected_price', 'lost_reason'])
            ->logOnlyDirty()
            ->useLogName('purchase_lead');
    }

    public function onStatusChanged(HasTransitions $from, HasTransitions $to, ?User $user): void
    {
        // Extension point for domain events (notifications land in Phase 9).
    }
}
