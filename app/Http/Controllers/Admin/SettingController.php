<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Settings\Models\Setting;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->can('settings.view'), 403);

        return Inertia::render('admin/settings/Index', [
            'bookingTerms' => Setting::bookingTermsText(),
            'defaultBookingTerms' => implode("\n", config('car4sales.booking_terms', [])),
            'canUpdate' => $request->user()->can('settings.update'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.update'), 403);

        $data = $request->validate([
            'booking_terms' => ['nullable', 'string', 'max:20000'],
        ]);

        // Empty falls back to the packaged default.
        Setting::set('booking_terms', filled($data['booking_terms'] ?? null) ? trim($data['booking_terms']) : null);

        return back()->with('success', 'Settings saved.');
    }
}
