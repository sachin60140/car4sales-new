<?php

namespace App\Http\Controllers\Vendor;

use App\Domain\VendorSubmissions\Actions\VendorRegistrationAction;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class VendorRegistrationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('vendor/Register');
    }

    public function store(Request $request, VendorRegistrationAction $action): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:120'],
            'gst_number' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $vendor = $action->register($data);

        event(new Registered($vendor));
        Auth::login($vendor);

        return redirect()->route('vendor.dashboard')
            ->with('success', 'Welcome! Your vendor account is awaiting activation by our team.');
    }
}
