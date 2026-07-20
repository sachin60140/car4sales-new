<?php

namespace App\Domain\Refurbishment\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Refurbishment\Enums\WorkshopJobStatus;
use App\Domain\Vendors\Models\Vendor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WorkshopJob extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'job_number', 'vehicle_id', 'vendor_id', 'branch_id', 'type', 'description',
        'estimate_total', 'approved_total', 'actual_total', 'status', 'start_date',
        'expected_completion', 'actual_completion', 'payment_status', 'qc_status',
        'qc_by', 'qc_at', 'approval_request_id', 'invoice_path', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkshopJobStatus::class,
            'estimate_total' => 'decimal:2',
            'approved_total' => 'decimal:2',
            'actual_total' => 'decimal:2',
            'start_date' => 'date',
            'expected_completion' => 'date',
            'actual_completion' => 'date',
            'qc_at' => 'datetime',
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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkshopJobItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'approved_total', 'actual_total', 'qc_status', 'payment_status'])
            ->logOnlyDirty()
            ->useLogName('workshop_job');
    }
}
