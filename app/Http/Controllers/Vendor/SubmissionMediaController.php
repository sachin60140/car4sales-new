<?php

namespace App\Http\Controllers\Vendor;

use App\Domain\VendorSubmissions\Models\VendorSubmissionMedia;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionMediaController extends Controller
{
    /**
     * Stream a submission image to whoever may view the submission (its vendor
     * owner, or staff with the review permission).
     */
    public function show(Request $request, VendorSubmissionMedia $media): StreamedResponse
    {
        $this->authorize('view', $media->submission);

        abort_unless(Storage::disk('private')->exists($media->file_path), 404);

        return Storage::disk('private')->response($media->file_path);
    }
}
