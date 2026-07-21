<?php

namespace App\Domain\VendorSubmissions\Models;

use App\Domain\VendorSubmissions\Enums\ChecklistResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSubmissionItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'vendor_submission_id', 'section', 'label', 'result', 'rating', 'remarks', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'result' => ChecklistResult::class,
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(VendorSubmission::class, 'vendor_submission_id');
    }
}
