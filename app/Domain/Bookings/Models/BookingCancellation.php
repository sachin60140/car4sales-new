<?php

namespace App\Domain\Bookings\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCancellation extends Model
{
    protected $fillable = [
        'booking_id', 'reason', 'refund_amount', 'forfeit_amount', 'requested_by',
        'approved_by', 'approved_at', 'status', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'refund_amount' => 'decimal:2',
            'forfeit_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
