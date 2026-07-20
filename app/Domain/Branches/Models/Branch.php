<?php

namespace App\Domain\Branches\Models;

use App\Domain\Teams\Models\Team;
use App\Models\User;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'slug', 'address', 'city', 'state', 'pin_code',
        'phone', 'email', 'latitude', 'longitude', 'gst_number',
        'is_active', 'sort_order', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('branch');
    }

    protected static function newFactory(): BranchFactory
    {
        return BranchFactory::new();
    }
}
