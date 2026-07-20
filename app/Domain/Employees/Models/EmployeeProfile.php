<?php

namespace App\Domain\Employees\Models;

use App\Models\User;
use Database\Factories\EmployeeProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EmployeeProfile extends Model
{
    /** @use HasFactory<EmployeeProfileFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'user_id', 'employee_code', 'designation', 'date_of_joining', 'date_of_leaving',
        'dob', 'gender', 'address', 'city', 'state', 'pin_code',
        'emergency_contact_name', 'emergency_contact_phone', 'blood_group',
        'photo_path', 'id_proof_type', 'id_proof_number', 'reports_to',
    ];

    protected function casts(): array
    {
        return [
            'date_of_joining' => 'date',
            'date_of_leaving' => 'date',
            'dob' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reports_to');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('employee');
    }

    protected static function newFactory(): EmployeeProfileFactory
    {
        return EmployeeProfileFactory::new();
    }
}
