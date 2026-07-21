<?php

namespace App\Http\Controllers\Vendor;

use App\Domain\VendorSubmissions\Models\VendorSubmission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->vendorProfile;

        $counts = VendorSubmission::query()
            ->where('vendor_user_id', $user->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recent = VendorSubmission::query()
            ->where('vendor_user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get(['id', 'submission_number', 'make', 'model', 'variant', 'expected_amount', 'status', 'created_at'])
            ->map(fn (VendorSubmission $s) => [
                'id' => $s->id,
                'submission_number' => $s->submission_number,
                'title' => $s->title(),
                'expected_amount' => $s->expected_amount,
                'status' => $s->status->value,
                'status_label' => $s->status->label(),
                'created_at' => $s->created_at->toDateString(),
            ]);

        return Inertia::render('vendor/Dashboard', [
            'profile' => [
                'company_name' => $profile?->company_name,
                'contact_person' => $profile?->contact_person,
                'status' => $profile?->status->value,
                'status_label' => $profile?->status->label(),
                'is_active' => $profile?->isActive() ?? false,
            ],
            'stats' => [
                'draft' => (int) ($counts['draft'] ?? 0),
                'pending_review' => (int) ($counts['pending_review'] ?? 0),
                'approved' => (int) ($counts['approved'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
            ],
            'recent' => $recent,
        ]);
    }
}
