<?php

namespace App\Domain\RTO\Models;

use App\Domain\Bookings\Models\Booking;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Deliveries\Models\Delivery;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Domain\RTO\Enums\RtoStatus;
use App\Domain\Sellers\Models\Seller;
use App\Domain\Vendors\Models\Vendor;
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

class RtoCase extends Model implements Transitionable
{
    use LogsActivity, RecordsStatusHistory, SoftDeletes;

    protected string $statusEnum = RtoStatus::class;

    protected $fillable = [
        'rto_number', 'vehicle_id', 'booking_id', 'delivery_id', 'seller_id', 'buyer_customer_id',
        'from_rto', 'to_rto', 'sale_date', 'delivery_date', 'assigned_to', 'agent_vendor_id',
        'expected_completion', 'application_number', 'hold_amount', 'status', 'rc_copy_path',
        'branch_id', 'created_by', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'status' => RtoStatus::class,
            'sale_date' => 'date',
            'delivery_date' => 'date',
            'expected_completion' => 'date',
            'hold_amount' => 'decimal:2',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'buyer_customer_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'agent_vendor_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(RtoStatusHistory::class)->latest();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RtoDocument::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(RtoDocumentMovement::class)->latest();
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(RtoExpense::class)->latest();
    }

    public function holds(): HasMany
    {
        return $this->hasMany(RtoHold::class)->latest();
    }

    public function totalExpenses(): string
    {
        return (string) $this->expenses()->sum('amount');
    }

    public function activeHoldAmount(): string
    {
        return (string) $this->holds()->where('status', 'held')->sum('amount');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'assigned_to', 'application_number', 'rc_copy_path'])
            ->logOnlyDirty()
            ->useLogName('rto');
    }

    public function onStatusChanged(HasTransitions $from, HasTransitions $to, ?User $user): void
    {
        if ($to instanceof RtoStatus) {
            app(NotificationDispatcher::class)->rtoStatusChanged($this, $to);
        }
    }
}
