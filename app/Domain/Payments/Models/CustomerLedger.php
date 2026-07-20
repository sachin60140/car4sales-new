<?php

namespace App\Domain\Payments\Models;

use App\Domain\Bookings\Models\Booking;
use App\Domain\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerLedger extends Model
{
    protected $fillable = ['booking_id', 'customer_id', 'branch_id', 'opened_at'];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class)->orderBy('posted_at');
    }

    /** Debits − credits (positive = amount the customer still owes). */
    public function outstanding(): float
    {
        $debits = (float) $this->entries()->where('type', 'debit')->sum('amount');
        $credits = (float) $this->entries()->where('type', 'credit')->sum('amount');

        return round($debits - $credits, 2);
    }
}
