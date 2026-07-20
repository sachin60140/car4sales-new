<?php

namespace App\Domain\PublicWebsite\Services;

use App\Domain\PublicWebsite\Models\OtpVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * OTP verification for public forms. Codes are hashed at rest; a successful
 * verification issues a short-lived token that the form submission must present.
 * SMS delivery uses a log driver here — swap in a real provider adapter later.
 */
class OtpService
{
    public function isRequired(): bool
    {
        return (bool) config('car4sales.public.require_otp');
    }

    /**
     * Generate and "send" an OTP for a mobile number.
     */
    public function request(string $mobile, string $purpose = 'enquiry'): void
    {
        $code = (string) random_int(100000, 999999);
        $ttl = (int) config('car4sales.public.otp_ttl_minutes', 10);

        OtpVerification::query()->create([
            'mobile' => $mobile,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($ttl),
        ]);

        // Delivery adapter (SMS/WhatsApp) — logged locally.
        Log::info("OTP for {$mobile} ({$purpose}): {$code}");
    }

    /**
     * Verify a code. On success returns a token to attach to the form submission.
     */
    public function verify(string $mobile, string $code, string $purpose = 'enquiry'): ?string
    {
        $otp = OtpVerification::query()
            ->where('mobile', $mobile)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if ($otp === null || $otp->isExpired() || $otp->attempts >= 5) {
            return null;
        }

        $otp->increment('attempts');

        if (! Hash::check($code, $otp->code_hash)) {
            return null;
        }

        $token = Str::random(48);
        $otp->update(['verified_at' => now(), 'token' => $token]);

        return $token;
    }

    /**
     * Confirm a token issued by verify() belongs to this mobile and is unused/fresh.
     */
    public function consumeToken(string $mobile, ?string $token, string $purpose = 'enquiry'): bool
    {
        if (! $this->isRequired()) {
            return true;
        }

        if ($token === null || $token === '') {
            return false;
        }

        $otp = OtpVerification::query()
            ->where('mobile', $mobile)
            ->where('purpose', $purpose)
            ->where('token', $token)
            ->whereNotNull('verified_at')
            ->where('verified_at', '>=', now()->subMinutes((int) config('car4sales.public.otp_ttl_minutes', 10)))
            ->first();

        if ($otp === null) {
            return false;
        }

        // One-time use.
        $otp->update(['token' => null]);

        return true;
    }
}
