<?php

namespace App\Domain\TestDrives\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestDrive extends Model
{
    protected $fillable = [
        'td_number', 'sales_lead_id', 'customer_id', 'vehicle_id', 'branch_id',
        'driving_licence_number', 'driving_licence_path', 'scheduled_at', 'start_at',
        'end_at', 'start_odometer', 'end_odometer', 'fuel_level', 'route',
        'accompanied_by', 'customer_signature_path', 'damage_acknowledged', 'feedback',
        'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'damage_acknowledged' => 'boolean',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
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
}
