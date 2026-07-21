<?php

namespace App\Domain\VendorSubmissions\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\Inventory\Models\Vehicle;
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
        'registration_number', 'registration_state', 'chassis_number', 'fuel_type', 'transmission', 'color',
        'odometer_km', 'ownership_serial', 'keys_available', 'expected_amount', 'overall_rating', 'overall_remark',
        'status', 'reviewed_by', 'reviewed_at', 'review_remarks', 'purchase_lead_id', 'branch_id',
        'settlement_status', 'owner_name', 'owner_phone', 'owner_email', 'owner_address', 'owner_pan',
        'has_hypothecation', 'document_verifications',
        'kyc_submitted_at', 'kyc_approved_at', 'kyc_approved_by', 'kyc_remarks',
        'bank_account_name', 'bank_account_number', 'bank_ifsc', 'bank_name',
        'payment_requested_at', 'payment_amount', 'payment_mode', 'payment_reference',
        'payment_date', 'paid_by', 'paid_at',
        'vehicle_id', 'possession', 'possession_confirmed_at', 'possessed_by',
    ];

    /**
     * The owner-KYC document catalog. Each entry: label, group (required | conditional
     * | optional) and sides (1, or 2 → front/back media types `<key>_front`/`<key>_back`).
     * Conditional docs (NOC, Form 35) are only required when the vehicle carries a loan.
     *
     * @return array<string, array{label: string, group: string, sides: int}>
     */
    public static function documentCatalog(bool $hasHypothecation = false): array
    {
        $catalog = [
            'rc' => ['label' => 'Registration Certificate (RC)', 'group' => 'required', 'sides' => 2],
            'aadhaar' => ['label' => 'Owner Aadhaar', 'group' => 'required', 'sides' => 2],
            'pan' => ['label' => 'Owner PAN card', 'group' => 'required', 'sides' => 1],
            'chassis_photo' => ['label' => 'Chassis number plate', 'group' => 'required', 'sides' => 1],
            'owner_photo' => ['label' => 'Owner with vehicle', 'group' => 'required', 'sides' => 1],
            'cancelled_cheque' => ['label' => 'Cancelled cheque', 'group' => 'required', 'sides' => 1],
            'noc' => ['label' => 'Bank NOC', 'group' => $hasHypothecation ? 'conditional' : 'optional', 'sides' => 1],
            'form_35' => ['label' => 'Form 35', 'group' => $hasHypothecation ? 'conditional' : 'optional', 'sides' => 1],
            'insurance' => ['label' => 'Insurance', 'group' => 'optional', 'sides' => 1],
            'puc' => ['label' => 'PUC', 'group' => 'optional', 'sides' => 1],
            'tax' => ['label' => 'Road tax', 'group' => 'optional', 'sides' => 1],
            'fitness' => ['label' => 'Fitness', 'group' => 'optional', 'sides' => 1],
            'permit' => ['label' => 'Permit', 'group' => 'optional', 'sides' => 1],
            'service_history' => ['label' => 'Service history', 'group' => 'optional', 'sides' => 1],
            'purchase_invoice' => ['label' => 'Purchase invoice', 'group' => 'optional', 'sides' => 1],
        ];

        return $catalog;
    }

    /** Document keys that must be uploaded + verified before the agreement is issued. */
    public static function requiredDocKeys(bool $hasHypothecation = false): array
    {
        return array_keys(array_filter(
            self::documentCatalog($hasHypothecation),
            fn ($d) => in_array($d['group'], ['required', 'conditional'], true),
        ));
    }

    /** Media type keys a document maps to (front/back for two-sided docs). */
    public static function docMediaTypes(string $key, int $sides): array
    {
        return $sides === 2 ? ["{$key}_front", "{$key}_back"] : [$key];
    }

    /** All owner-KYC document media types (single + front/back), for the media relation. */
    public static function allDocMediaTypes(): array
    {
        $types = [];
        foreach (self::documentCatalog(true) as $key => $d) {
            $types = [...$types, ...self::docMediaTypes($key, $d['sides'])];
        }

        return [...$types, 'other_doc'];
    }

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'settlement_status' => SettlementStatus::class,
            'expected_amount' => 'decimal:2',
            'payment_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'kyc_submitted_at' => 'datetime',
            'kyc_approved_at' => 'datetime',
            'payment_requested_at' => 'datetime',
            'payment_date' => 'date',
            'paid_at' => 'datetime',
            'possession' => 'array',
            'possession_confirmed_at' => 'datetime',
            'has_hypothecation' => 'boolean',
            'document_verifications' => 'array',
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

    /** All owner-KYC document media (front/back RC & Aadhaar, PAN, chassis, cheque, extras…). */
    public function documentMedia(): HasMany
    {
        return $this->media()->whereIn('type', self::allDocMediaTypes());
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function kycApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kyc_approved_by');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function possessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'possessed_by');
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
