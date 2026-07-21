<?php

namespace App\Domain\SalesLeads\Models;

use App\Domain\Bookings\Models\Booking;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\SalesLeads\Enums\SalesLeadStatus;
use App\Domain\TestDrives\Models\TestDrive;
use App\Domain\Visits\Models\CustomerVisit;
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

class SalesLead extends Model implements Transitionable
{
    use LogsActivity, RecordsStatusHistory, SoftDeletes;

    protected string $statusEnum = SalesLeadStatus::class;

    protected $fillable = [
        'lead_number', 'customer_id', 'name', 'mobile', 'alt_mobile', 'email', 'city',
        'budget_min', 'budget_max', 'interested_vehicle_id', 'preferences', 'finance_required',
        'exchange_required', 'source', 'campaign', 'utm', 'branch_id', 'telecaller_id',
        'sales_executive_id', 'priority', 'next_follow_up_at', 'first_response_at', 'status',
        'lost_reason_id', 'remarks', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SalesLeadStatus::class,
            'preferences' => 'array',
            'utm' => 'array',
            'finance_required' => 'boolean',
            'exchange_required' => 'boolean',
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'next_follow_up_at' => 'datetime',
            'first_response_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function interestedVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'interested_vehicle_id');
    }

    public function telecaller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'telecaller_id');
    }

    public function salesExecutive(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_executive_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lostReason(): BelongsTo
    {
        return $this->belongsTo(LeadLostReason::class, 'lost_reason_id');
    }

    public function followups(): HasMany
    {
        return $this->hasMany(LeadFollowup::class)->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class)->latest();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class)->latest();
    }

    public function visits(): HasMany
    {
        return $this->hasMany(CustomerVisit::class)->latest();
    }

    public function testDrives(): HasMany
    {
        return $this->hasMany(TestDrive::class)->latest();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class)->latest();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'telecaller_id', 'sales_executive_id', 'priority', 'lost_reason_id'])
            ->logOnlyDirty()
            ->useLogName('sales_lead');
    }

    public function onStatusChanged(HasTransitions $from, HasTransitions $to, ?User $user): void
    {
        // Notification hooks land in Phase 9.
    }
}
