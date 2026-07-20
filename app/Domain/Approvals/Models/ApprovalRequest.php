<?php

namespace App\Domain\Approvals\Models;

use App\Domain\Approvals\Enums\ApprovalStatus;
use App\Domain\Branches\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ApprovalRequest extends Model
{
    use LogsActivity;

    protected $fillable = [
        'approval_number', 'module', 'type', 'subject_type', 'subject_id',
        'branch_id', 'requested_by', 'requested_amount', 'recommended_amount',
        'approved_amount', 'reason', 'reasons', 'attachments', 'status',
        'current_role_id', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'reasons' => 'array',
            'attachments' => 'array',
            'requested_amount' => 'decimal:2',
            'recommended_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('sequence');
    }

    public function currentStep(): ?ApprovalStep
    {
        return $this->steps->firstWhere('status', 'pending');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'approved_amount', 'current_role_id'])
            ->logOnlyDirty()
            ->useLogName('approval');
    }
}
