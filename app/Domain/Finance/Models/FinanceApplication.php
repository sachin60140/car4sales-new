<?php

namespace App\Domain\Finance\Models;

use App\Domain\Bookings\Models\Booking;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Finance\Enums\FinanceStatus;
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

class FinanceApplication extends Model implements Transitionable
{
    use LogsActivity, RecordsStatusHistory, SoftDeletes;

    protected string $statusEnum = FinanceStatus::class;

    protected $fillable = [
        'application_number', 'booking_id', 'customer_id', 'lender_id', 'applicant',
        'co_applicant', 'guarantor', 'income', 'employer', 'loan_amount', 'down_payment',
        'lender_application_number', 'sanction_amount', 'interest_rate', 'tenure_months',
        'emi', 'queries', 'rejection_reason', 'disbursed_amount', 'status',
        'assigned_to', 'branch_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => FinanceStatus::class,
            'applicant' => 'array',
            'co_applicant' => 'array',
            'guarantor' => 'array',
            'income' => 'array',
            'loan_amount' => 'decimal:2',
            'down_payment' => 'decimal:2',
            'sanction_amount' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'emi' => 'decimal:2',
            'disbursed_amount' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Lender::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(Disbursement::class)->latest();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(FinanceStatusHistory::class)->latest();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'lender_id', 'sanction_amount', 'disbursed_amount'])
            ->logOnlyDirty()
            ->useLogName('finance');
    }

    public function onStatusChanged(HasTransitions $from, HasTransitions $to, ?User $user): void
    {
        if ($to instanceof FinanceStatus) {
            app(\App\Domain\Notifications\Services\NotificationDispatcher::class)->financeStatusChanged($this, $to);
        }
    }
}
