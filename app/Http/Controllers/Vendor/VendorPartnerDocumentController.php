<?php

namespace App\Http\Controllers\Vendor;

use App\Domain\VendorSubmissions\Models\VendorPartnerDocument;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendorPartnerDocumentController extends Controller
{
    /**
     * Stream a partner-KYC document to its owner partner, or to staff who may
     * view vendor partners.
     */
    public function show(Request $request, VendorPartnerDocument $document): StreamedResponse
    {
        $user = $request->user();

        abort_unless(
            $document->profile->user_id === $user->id || $user->can('vendor-partners.view'),
            403,
        );

        abort_unless(Storage::disk('private')->exists($document->file_path), 404);

        return Storage::disk('private')->response($document->file_path);
    }
}
