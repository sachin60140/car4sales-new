<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Customers\Models\CustomerDocument;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerDocumentController extends Controller
{
    /**
     * Stream a customer KYC document to staff who may view the customer and hold
     * the KYC permission.
     */
    public function show(Request $request, CustomerDocument $customerDocument): StreamedResponse
    {
        $this->authorize('view', $customerDocument->customer);
        abort_unless($request->user()->can('customers.view-kyc'), 403);

        abort_unless($customerDocument->file_path && Storage::disk('private')->exists($customerDocument->file_path), 404);

        return Storage::disk('private')->response($customerDocument->file_path);
    }
}
