<?php

namespace App\Domain\VendorSubmissions\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSubmissionMedia extends Model
{
    protected $table = 'vendor_submission_media';

    protected $fillable = [
        'vendor_submission_id', 'type', 'file_path', 'thumbnail_path', 'caption',
        'original_name', 'mime_type', 'size_bytes', 'uploaded_by',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(VendorSubmission::class, 'vendor_submission_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
