<?php

namespace App\Http\Controllers\Vendor;

use App\Domain\VendorSubmissions\Actions\VendorPartnerKycAction;
use App\Domain\VendorSubmissions\Models\VendorProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VendorKycController extends Controller
{
    public function edit(Request $request): Response
    {
        $profile = $request->user()->vendorProfile;
        abort_if($profile === null, 404);

        $profile->load('documents.verifier:id,name');

        return Inertia::render('vendor/Kyc', [
            'kyc' => [
                'rows' => $profile->kycRows(),
                'status' => $profile->kyc_status,
            ],
            'partner' => [
                'status' => $profile->status->value,
                'status_label' => $profile->status->label(),
            ],
        ]);
    }

    public function upload(Request $request, VendorPartnerKycAction $action): RedirectResponse
    {
        $profile = $request->user()->vendorProfile;
        abort_if($profile === null, 404);

        $data = $request->validate([
            'type' => ['required', Rule::in(VendorProfile::allMediaTypes())],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
            'number' => ['nullable', 'string', 'max:100'],
        ]);

        $action->uploadDocument($profile, $data['type'], $request->file('file'), $data['number'] ?? null, $request->user());

        return back()->with('success', 'Document uploaded.');
    }
}
