<?php

namespace App\Domain\PublicWebsite\Models;

use App\Domain\PurchaseLeads\Models\PurchaseLead;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellCarRequest extends Model
{
    protected $fillable = [
        'public_enquiry_id', 'seller_name', 'mobile', 'city', 'registration_number',
        'make', 'model', 'variant', 'manufacturing_year', 'odometer_km', 'expected_price',
        'loan_status', 'preferred_inspection_location', 'preferred_date', 'photos',
        'remarks', 'purchase_lead_id',
    ];

    protected function casts(): array
    {
        return [
            'photos' => 'array',
            'preferred_date' => 'date',
            'expected_price' => 'decimal:2',
        ];
    }

    public function purchaseLead(): BelongsTo
    {
        return $this->belongsTo(PurchaseLead::class);
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(PublicEnquiry::class, 'public_enquiry_id');
    }
}
