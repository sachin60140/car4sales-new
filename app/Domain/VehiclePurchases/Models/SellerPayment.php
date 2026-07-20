<?php

namespace App\Domain\VehiclePurchases\Models;

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Sellers\Models\Seller;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SellerPayment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'payment_number', 'vehicle_purchase_id', 'seller_id', 'type', 'amount',
        'method', 'payment_account', 'reference_number', 'proof_path',
        'recipient_type', 'recipient_details', 'status', 'approval_request_id',
        'created_by', 'approved_by', 'reversed_by', 'reversal_of', 'paid_at', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'recipient_details' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function vehiclePurchase(): BelongsTo
    {
        return $this->belongsTo(VehiclePurchase::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function maker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(SellerPayment::class, 'reversal_of');
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'amount', 'type'])
            ->logOnlyDirty()
            ->useLogName('seller_payment');
    }
}
