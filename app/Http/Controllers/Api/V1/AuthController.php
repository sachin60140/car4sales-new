<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Models\LoginHistory;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
            'device_uuid' => ['nullable', 'string', 'max:100'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
            'app_version' => ['nullable', 'string', 'max:30'],
            'fcm_token' => ['nullable', 'string', 'max:512'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $data['email'])->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            LoginHistory::query()->create([
                'user_id' => $user?->id,
                'email' => $data['email'],
                'ip_address' => $request->ip(),
                'user_agent' => (string) str($request->userAgent() ?? '')->limit(500),
                'device_uuid' => $data['device_uuid'] ?? null,
                'guard' => 'api',
                'event' => 'failed',
            ]);

            return ApiResponse::error('The provided credentials are incorrect.', 401);
        }

        if (! $user->is_active) {
            return ApiResponse::error('Your account has been deactivated.', 403);
        }

        if (! $user->hasRole('Super Admin') && ! $user->can('access-mobile')) {
            return ApiResponse::error('You are not allowed to use the mobile application.', 403);
        }

        $deviceUuid = $data['device_uuid'] ?? (string) Str::uuid();

        // One token per device: replace any previous token for this device.
        $user->tokens()->where('name', $deviceUuid)->delete();
        $token = $user->createToken($deviceUuid)->plainTextToken;

        $user->devices()->updateOrCreate(
            ['device_uuid' => $deviceUuid],
            [
                'device_name' => $data['device_name'],
                'platform' => $data['platform'] ?? 'android',
                'app_version' => $data['app_version'] ?? null,
                'fcm_token' => $data['fcm_token'] ?? null,
                'ip_address' => $request->ip(),
                'last_used_at' => now(),
                'revoked_at' => null,
            ],
        );

        LoginHistory::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $request->ip(),
            'user_agent' => (string) str($request->userAgent() ?? '')->limit(500),
            'device_uuid' => $deviceUuid,
            'guard' => 'api',
            'event' => 'login',
        ]);

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return ApiResponse::success([
            'token' => $token,
            'device_uuid' => $deviceUuid,
            'user' => $this->userPayload($user),
        ], 'Login successful.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $deviceUuid = $user->currentAccessToken()?->name;

        if ($deviceUuid !== null) {
            $user->devices()
                ->where('device_uuid', $deviceUuid)
                ->update(['revoked_at' => now()]);
        }

        LoginHistory::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $request->ip(),
            'user_agent' => (string) str($request->userAgent() ?? '')->limit(500),
            'device_uuid' => $deviceUuid,
            'guard' => 'api',
            'event' => 'logout',
        ]);

        $user->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logged out.');
    }

    public function updatePushToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_uuid' => ['required', 'string', 'max:100'],
            'fcm_token' => ['required', 'string', 'max:512'],
        ]);

        $updated = $request->user()->devices()
            ->where('device_uuid', $data['device_uuid'])
            ->whereNull('revoked_at')
            ->update(['fcm_token' => $data['fcm_token'], 'last_used_at' => now()]);

        if ($updated === 0) {
            return ApiResponse::error('Device not registered.', 404);
        }

        return ApiResponse::success(null, 'Push token updated.');
    }

    private function userPayload(User $user): array
    {
        $user->load(['branch:id,name,code', 'department:id,name,code', 'team:id,name', 'employeeProfile:id,user_id,employee_code,designation,photo_path']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'branch' => $user->branch,
            'department' => $user->department,
            'team' => $user->team,
            'employee_code' => $user->employeeProfile?->employee_code,
            'designation' => $user->employeeProfile?->designation,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->hasRole('Super Admin')
                ? ['*']
                : $user->getAllPermissions()->pluck('name')->values(),
        ];
    }
}
