<?php

namespace App\Domain\Inspections\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\Inspections\Enums\InspectionStatus;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VehicleInspection extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'inspection_number', 'purchase_lead_id', 'inspector_id', 'branch_id',
        'scheduled_at', 'started_at', 'completed_at', 'location', 'odometer_km',
        'overall_grade', 'result', 'total_repair_estimate', 'remarks', 'locked_at',
        'reviewed_by', 'reviewed_at', 'signature_path', 'report_path', 'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => InspectionStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'locked_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'total_repair_estimate' => 'decimal:2',
        ];
    }

    public function purchaseLead(): BelongsTo
    {
        return $this->belongsTo(PurchaseLead::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(InspectionSection::class)->orderBy('sort_order');
    }

    public function media(): HasMany
    {
        return $this->hasMany(InspectionMedia::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'result', 'overall_grade', 'total_repair_estimate', 'inspector_id'])
            ->logOnlyDirty()
            ->useLogName('inspection');
    }
}
