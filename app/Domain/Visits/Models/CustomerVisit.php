<?php

namespace App\Domain\Visits\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Domain\Visits\Enums\VisitStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerVisit extends Model
{
    protected $fillable = [
        'visit_number', 'sales_lead_id', 'customer_id', 'branch_id', 'scheduled_at',
        'confirmed', 'arrived_at', 'attended_by', 'interested_vehicle_ids', 'outcome',
        'next_action', 'remarks', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => VisitStatus::class,
            'confirmed' => 'boolean',
            'scheduled_at' => 'datetime',
            'arrived_at' => 'datetime',
            'interested_vehicle_ids' => 'array',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function attendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attended_by');
    }
}
