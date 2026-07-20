<?php

namespace App\Http\Controllers;

use App\Domain\Documents\Models\GeneratedDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileDownloadController extends Controller
{
    /**
     * Stream a generated document to an authenticated, permitted user,
     * logging the download for audit.
     */
    public function document(Request $request, GeneratedDocument $document): StreamedResponse
    {
        abort_unless($request->user()->can('documents.download') || $request->user()->can('vehicle-purchases.download'), 403);

        abort_unless(Storage::disk('private')->exists($document->file_path), 404);

        activity('document')
            ->causedBy($request->user())
            ->performedOn($document)
            ->event('downloaded')
            ->log('Document downloaded');

        return Storage::disk('private')->download($document->file_path, $document->document_number.'.pdf');
    }

    /**
     * Fallback streamed access for private media (local disk without signed URLs).
     */
    public function file(Request $request, string $path): StreamedResponse
    {
        $path = urldecode($path);
        abort_unless(Storage::disk('private')->exists($path), 404);

        return Storage::disk('private')->response($path);
    }
}
