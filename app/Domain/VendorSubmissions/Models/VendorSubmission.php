<?php

namespace App\Domain\VendorSubmissions\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Domain\VendorSubmissions\Enums\SettlementStatus;
use App\Domain\VendorSubmissions\Enums\SubmissionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VendorSubmission extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'submission_number', 'vendor_user_id', 'make', 'model', 'variant', 'manufacturing_year',
        'registration_number', 'registration_state', 'fuel_type', 'transmission', 'color',
        'odometer_km', 'ownership_serial', 'expected_amount', 'overall_rating', 'overall_remark',
        'status', 'reviewed_by', 'reviewed_at', 'review_remarks', 'purchase_lead_id', 'branch_id',
        'settlement_status', 'bank_account_name', 'bank_account_number', 'bank_ifsc', 'bank_name',
        'payment_requested_at', 'payment_amount', 'payment_mode', 'payment_reference',
        'payment_date', 'paid_by', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'settlement_status' => SettlementStatus::class,
            'expected_amount' => 'decimal:2',
            'payment_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'payment_requested_at' => 'datetime',
            'payment_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchaseLead(): BelongsTo
    {
        return $this->belongsTo(PurchaseLead::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(VendorSubmissionItem::class)->orderBy('sort_order');
    }

    public function media(): HasMany
    {
        return $this->hasMany(VendorSubmissionMedia::class);
    }

    public function galleryMedia(): HasMany
    {
        return $this->media()->where('type', 'gallery');
    }

    public function damageMedia(): HasMany
    {
        return $this->media()->where('type', 'damage');
    }

    public function chequeMedia(): HasMany
    {
        return $this->media()->where('type', 'cancelled_cheque');
    }

    public function paymentProofMedia(): HasMany
    {
        return $this->media()->where('type', 'payment_proof');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function title(): string
    {
        return trim("{$this->make} {$this->model} {$this->variant}");
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'expected_amount', 'reviewed_by', 'purchase_lead_id'])
            ->logOnlyDirty()
            ->useLogName('vendor_submission');
    }
}
