<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\ExpenseCategory;
use App\Domain\Refurbishment\Models\WorkshopJob;
use App\Domain\Vendors\Models\Vendor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VehicleExpense extends Model
{
    use LogsActivity;

    protected $fillable = [
        'expense_number', 'vehicle_id', 'category', 'description', 'amount',
        'vendor_id', 'workshop_job_id', 'status', 'added_to_landed_cost',
        'invoice_path', 'approved_by', 'approved_at', 'reversal_of', 'created_by', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'category' => ExpenseCategory::class,
            'amount' => 'decimal:2',
            'added_to_landed_cost' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function workshopJob(): BelongsTo
    {
        return $this->belongsTo(WorkshopJob::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'amount', 'category', 'added_to_landed_cost'])
            ->logOnlyDirty()
            ->useLogName('vehicle_expense');
    }
}
