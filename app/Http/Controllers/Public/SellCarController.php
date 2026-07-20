<?php

namespace App\Http\Controllers\Public;

use App\Domain\PublicWebsite\Actions\SellYourCarAction;
use App\Domain\PublicWebsite\Services\OtpService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SellCarController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function create(): Response
    {
        return Inertia::render('public/SellCar', [
            'otpRequired' => $this->otp->isRequired(),
        ]);
    }

    public function store(Request $request, SellYourCarAction $action): RedirectResponse
    {
        $data = $request->validate([
            'seller_name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:20'],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'variant' => ['nullable', 'string', 'max:100'],
            'manufacturing_year' => ['nullable', 'integer', 'between:1990,'.((int) date('Y') + 1)],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'expected_price' => ['nullable', 'numeric', 'min:0'],
            'loan_status' => ['nullable', 'string', 'in:none,active,closed_pending_noc'],
            'preferred_inspection_location' => ['nullable', 'string', 'max:255'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'consent' => ['accepted'],
            'otp_token' => ['nullable', 'string'],
            'company' => ['nullable', 'size:0'], // honeypot
        ]);

        $key = 'sell-car:'.$request->ip().':'.$data['mobile'];
        if (RateLimiter::tooManyAttempts($key, 4)) {
            throw ValidationException::withMessages(['mobile' => 'Too many submissions. Please try again later.']);
        }
        RateLimiter::hit($key, 3600);

        if (! $this->otp->consumeToken($data['mobile'], $data['otp_token'] ?? null, 'sell_car')) {
            throw ValidationException::withMessages(['otp_token' => 'Please verify your mobile number with the OTP.']);
        }

        $result = $action->execute([...$data, 'otp_verified' => $this->otp->isRequired()], $request);

        return back()->with('enquiry_success', "Thank you! Your request {$result['lead']->lead_number} is registered. Our team will call you to schedule an inspection.");
    }
}
