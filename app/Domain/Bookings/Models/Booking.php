<?php

namespace App\Domain\Bookings\Models;

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\SalesLeads\Models\SalesLead;
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

class Booking extends Model implements Transitionable
{
    use LogsActivity, RecordsStatusHistory, SoftDeletes;

    protected string $statusEnum = BookingStatus::class;

    protected $fillable = [
        'booking_number', 'sales_lead_id', 'customer_id', 'vehicle_id', 'selling_price',
        'booking_amount', 'discount_amount', 'discount_approved_by', 'payment_mode',
        'exchange_adjustment', 'delivery_promised_at', 'telecaller_id', 'sales_executive_id',
        'branch_id', 'accessories_promised', 'terms', 'customer_signature_path', 'status',
        'approval_request_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'selling_price' => 'decimal:2',
            'booking_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'exchange_adjustment' => 'decimal:2',
            'delivery_promised_at' => 'datetime',
            'accessories_promised' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function salesExecutive(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_executive_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class)->latest();
    }

    public function cancellations(): HasMany
    {
        return $this->hasMany(BookingCancellation::class)->latest();
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->latest();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class)->latest();
    }

    /** Net amount payable after discount and exchange. */
    public function netPayable(): float
    {
        return (float) $this->selling_price - (float) $this->discount_amount - (float) $this->exchange_adjustment;
    }

    /** Total non-reversed payments received. */
    public function paidAmount(): float
    {
        return (float) $this->payments()->where('status', 'received')->sum('amount');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'selling_price', 'discount_amount', 'discount_approved_by'])
            ->logOnlyDirty()
            ->useLogName('booking');
    }

    public function onStatusChanged(HasTransitions $from, HasTransitions $to, ?User $user): void
    {
        if ($to === BookingStatus::Confirmed) {
            app(\App\Domain\Notifications\Services\NotificationDispatcher::class)->bookingConfirmed($this);
        }
    }

    /**
     * Called by the approval engine when the discount approval is decided.
     */
    public function onApprovalDecided(ApprovalRequest $request, string $decision, User $approver): void
    {
        app(\App\Domain\Bookings\Actions\ConfirmBookingAction::class)
            ->onDiscountDecision($this, $decision, $approver);
    }
}
