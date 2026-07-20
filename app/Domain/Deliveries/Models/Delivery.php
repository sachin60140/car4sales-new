<?php

namespace App\Domain\Deliveries\Models;

use App\Domain\Bookings\Models\Booking;
use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Deliveries\Enums\DeliveryStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Delivery extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'delivery_number', 'booking_id', 'vehicle_id', 'customer_id', 'branch_id', 'status',
        'chk_booking_confirmed', 'chk_kyc_verified', 'chk_payment_complete', 'chk_finance_disbursed',
        'chk_quality_check', 'chk_insurance', 'chk_rto_papers_signed', 'chk_accessories',
        'chk_cleaned', 'chk_documents_prepared', 'approved_by', 'approved_at',
        'scheduled_at', 'delivered_at', 'odometer', 'fuel_level',
        'dc_keys', 'dc_spare_key', 'dc_rc_copy', 'dc_insurance', 'dc_invoice',
        'dc_tool_kit', 'dc_spare_wheel', 'dc_accessories',
        'customer_photo_path', 'delivery_photo_path', 'customer_signature_path',
        'employee_signature_path', 'delivered_by', 'created_by', 'remarks',
    ];

    /** Delivery-approval checklist columns (spec §24). */
    public const APPROVAL_CHECKS = [
        'chk_booking_confirmed', 'chk_kyc_verified', 'chk_payment_complete', 'chk_finance_disbursed',
        'chk_quality_check', 'chk_insurance', 'chk_rto_papers_signed', 'chk_accessories',
        'chk_cleaned', 'chk_documents_prepared',
    ];

    protected function casts(): array
    {
        $casts = [
            'status' => DeliveryStatus::class,
            'approved_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];

        foreach ([...self::APPROVAL_CHECKS, 'dc_keys', 'dc_spare_key', 'dc_rc_copy', 'dc_insurance', 'dc_invoice', 'dc_tool_kit', 'dc_spare_wheel', 'dc_accessories'] as $bool) {
            $casts[$bool] = 'boolean';
        }

        return $casts;
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DeliveryDocument::class);
    }

    /** True when every delivery-approval checklist item is satisfied. */
    public function approvalChecklistComplete(): bool
    {
        foreach (self::APPROVAL_CHECKS as $check) {
            if (! $this->{$check}) {
                return false;
            }
        }

        return true;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'approved_by', 'delivered_at'])
            ->logOnlyDirty()
            ->useLogName('delivery');
    }
}
