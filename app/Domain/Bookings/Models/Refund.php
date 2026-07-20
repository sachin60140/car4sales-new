<?php

namespace App\Domain\Bookings\Models;

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'refund_number', 'booking_id', 'booking_cancellation_id', 'amount', 'method',
        'reference', 'status', 'approval_request_id', 'approved_by', 'paid_at',
        'created_by', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function onApprovalDecided(ApprovalRequest $request, string $decision, User $approver): void
    {
        if ($decision === 'approved') {
            $this->update(['status' => 'approved', 'approved_by' => $approver->id]);
        } else {
            $this->update(['status' => 'rejected']);
        }
    }
}
