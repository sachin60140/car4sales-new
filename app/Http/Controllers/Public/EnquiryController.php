<?php

namespace App\Http\Controllers\Public;

use App\Domain\PublicWebsite\Actions\CreateEnquiryAction;
use App\Domain\PublicWebsite\Enums\EnquiryType;
use App\Domain\PublicWebsite\Services\OtpService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class EnquiryController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function requestOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'purpose' => ['nullable', 'string', 'max:40'],
        ]);

        $key = 'otp-request:'.$request->ip().':'.$data['mobile'];
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json(['success' => false, 'message' => 'Too many OTP requests. Please wait a while.'], 429);
        }
        RateLimiter::hit($key, 600);

        $this->otp->request($data['mobile'], $data['purpose'] ?? 'enquiry');

        return response()->json(['success' => true, 'message' => 'OTP sent.']);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'code' => ['required', 'string', 'size:6'],
            'purpose' => ['nullable', 'string', 'max:40'],
        ]);

        $token = $this->otp->verify($data['mobile'], $data['code'], $data['purpose'] ?? 'enquiry');

        if ($token === null) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP.'], 422);
        }

        return response()->json(['success' => true, 'otp_token' => $token]);
    }

    public function store(Request $request, CreateEnquiryAction $action): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
            'otp_token' => ['nullable', 'string'],
            'company' => ['nullable', 'size:0'], // honeypot: must be empty
            'utm' => ['nullable', 'array'],
        ]);

        $type = EnquiryType::tryFrom($data['type']);
        abort_if($type === null || $type === EnquiryType::SellCar, 422);

        $this->guardRate($request, $data['mobile']);
        $this->guardOtp($data['mobile'], $data['otp_token'] ?? null, $type->value);

        $action->execute($type, [...$data, 'otp_verified' => $this->otp->isRequired()], $request);

        return back()->with('enquiry_success', 'Thank you! Our team will contact you shortly.');
    }

    private function guardRate(Request $request, string $mobile): void
    {
        $key = 'enquiry:'.$request->ip().':'.$mobile;
        if (RateLimiter::tooManyAttempts($key, 6)) {
            throw ValidationException::withMessages(['mobile' => 'Too many submissions. Please try again later.']);
        }
        RateLimiter::hit($key, 3600);
    }

    private function guardOtp(string $mobile, ?string $token, string $purpose): void
    {
        if (! $this->otp->consumeToken($mobile, $token, $purpose)) {
            throw ValidationException::withMessages(['otp_token' => 'Please verify your mobile number with the OTP.']);
        }
    }
}
