<?php

namespace App\Domain\Payments\Models;

use App\Domain\Bookings\Models\Booking;
use App\Domain\Bookings\Models\BookingPayment;
use App\Domain\Documents\Models\GeneratedDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'receipt_number', 'booking_id', 'booking_payment_id', 'amount',
        'generated_document_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(BookingPayment::class, 'booking_payment_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }
}
