<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\Inventory\Enums\MovementType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleMovement extends Model
{
    protected $fillable = [
        'vehicle_id', 'type', 'from_branch_id', 'to_branch_id', 'from_location',
        'to_location', 'reference', 'moved_by', 'moved_at', 'expected_return_at',
        'returned_at', 'status', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'moved_at' => 'datetime',
            'expected_return_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function mover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }
}
