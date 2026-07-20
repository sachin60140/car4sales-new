<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\VehiclePurchases\Models\VehiclePurchase;
use App\Models\User;
use App\Support\Workflow\HasTransitions;
use App\Support\Workflow\RecordsStatusHistory;
use App\Support\Workflow\Transitionable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Vehicle extends Model implements Transitionable
{
    use LogsActivity, RecordsStatusHistory, SoftDeletes;

    protected string $statusEnum = VehicleStatus::class;

    protected $fillable = [
        'stock_number', 'vehicle_purchase_id', 'registration_number', 'chassis_number',
        'engine_number', 'make', 'model', 'variant', 'manufacturing_year', 'registration_year',
        'registration_state', 'fuel_type', 'transmission', 'body_type', 'color', 'odometer_km',
        'ownership_serial', 'insurance_status', 'insurance_valid_till', 'purchase_price',
        'landed_cost', 'minimum_selling_price', 'asking_price', 'branch_id', 'parking_location',
        'inspection_grade', 'refurb_required', 'status', 'published_web', 'published_mobile',
        'slug', 'title', 'description', 'key_features', 'is_featured', 'reserved_booking_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => VehicleStatus::class,
            'insurance_valid_till' => 'date',
            'purchase_price' => 'decimal:2',
            'landed_cost' => 'decimal:2',
            'minimum_selling_price' => 'decimal:2',
            'asking_price' => 'decimal:2',
            'refurb_required' => 'boolean',
            'published_web' => 'boolean',
            'published_mobile' => 'boolean',
            'is_featured' => 'boolean',
            'key_features' => 'array',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(VehiclePurchase::class, 'vehicle_purchase_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(VehicleStatusHistory::class)->latest();
    }

    public function media(): HasMany
    {
        return $this->hasMany(VehicleMedia::class)->orderBy('sort_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(VehicleMovement::class)->latest();
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(VehicleExpense::class)->latest();
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(VehiclePrice::class)->latest();
    }

    public function workshopJobs(): HasMany
    {
        return $this->hasMany(\App\Domain\Refurbishment\Models\WorkshopJob::class)->latest();
    }

    /** Days the vehicle has been in stock (ageing). */
    public function ageDays(): int
    {
        return (int) $this->created_at->diffInDays(now());
    }

    /** Vehicles that may appear on the public website. */
    public function scopePublished(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('published_web', true)
            ->whereIn('status', [
                VehicleStatus::ReadyForSale->value,
                VehicleStatus::Published->value,
                VehicleStatus::Reserved->value,
                VehicleStatus::Booked->value,
            ]);
    }

    public function publicMedia(): HasMany
    {
        return $this->hasMany(VehicleMedia::class)->where('is_public', true)->orderBy('sort_order');
    }

    public function availability(): string
    {
        return match ($this->status) {
            VehicleStatus::Booked, VehicleStatus::Reserved => 'reserved',
            default => 'available',
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'landed_cost', 'asking_price', 'branch_id', 'published_web'])
            ->logOnlyDirty()
            ->useLogName('vehicle');
    }

    public function onStatusChanged(HasTransitions $from, HasTransitions $to, ?User $user): void
    {
        //
    }
}
