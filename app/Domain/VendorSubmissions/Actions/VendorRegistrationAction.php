<?php

namespace App\Domain\VendorSubmissions\Actions;

use App\Domain\Notifications\Enums\NotificationLevel;
use App\Domain\Notifications\Services\NotificationService;
use App\Domain\VendorSubmissions\Enums\VendorProfileStatus;
use App\Domain\VendorSubmissions\Models\VendorProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Self-service vendor onboarding. A new partner can log in immediately but stays
 * in pending_activation until a Purchase Manager activates them (spec: vendors
 * cannot submit vehicles until approved).
 */
class VendorRegistrationAction
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'], // hashed by the model cast
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $user->assignRole('Vendor Partner');

            $user->vendorProfile()->create([
                'company_name' => $data['company_name'] ?? null,
                'contact_person' => $data['contact_person'] ?? $data['name'],
                'phone' => $data['phone'] ?? null,
                'city' => $data['city'] ?? null,
                'gst_number' => $data['gst_number'] ?? null,
                'status' => VendorProfileStatus::PendingActivation->value,
            ]);

            // Alert staff who can activate partners.
            $reviewers = $this->notifications->usersWithPermission('vendor-partners.activate');
            $this->notifications->notifyMany($reviewers, 'vendor.registered', 'New vendor partner registered', [
                'level' => NotificationLevel::Info,
                'body' => ($data['company_name'] ?? $data['name']).' has registered and is awaiting activation.',
                'action_url' => '/admin/vendor-partners',
            ]);

            return $user->fresh('vendorProfile');
        });
    }

    /**
     * Admin-created vendor partner. Unlike self-registration, the admin sets the
     * initial status directly (defaults to Active) and no "awaiting activation"
     * alert is raised — the partner is simply told their account is ready.
     *
     * @param  array<string, mixed>  $data
     */
    public function createByAdmin(array $data, User $actor): User
    {
        return DB::transaction(function () use ($data, $actor) {
            $status = $data['status'] ?? VendorProfileStatus::Active->value;

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'], // hashed by the model cast
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $user->assignRole('Vendor Partner');

            $user->vendorProfile()->create([
                'company_name' => $data['company_name'] ?? null,
                'contact_person' => $data['contact_person'] ?? $data['name'],
                'phone' => $data['phone'] ?? null,
                'city' => $data['city'] ?? null,
                'gst_number' => $data['gst_number'] ?? null,
                'status' => $status,
                'activated_by' => $status === VendorProfileStatus::Active->value ? $actor->id : null,
                'activated_at' => $status === VendorProfileStatus::Active->value ? now() : null,
            ]);

            $this->notifications->notify($user, 'vendor.created', 'Vendor account created', [
                'level' => NotificationLevel::Success,
                'body' => $status === VendorProfileStatus::Active->value
                    ? 'Your vendor account is active — you can now submit vehicles.'
                    : 'Your vendor account has been created and is awaiting activation.',
                'action_url' => '/vendor',
            ]);

            return $user->fresh('vendorProfile');
        });
    }

    /**
     * Update an existing vendor partner's login and profile details. Status is
     * managed separately via setStatus(); an empty password leaves it unchanged.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(VendorProfile $profile, array $data): VendorProfile
    {
        return DB::transaction(function () use ($profile, $data) {
            $userAttributes = [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ];
            if (! empty($data['password'])) {
                $userAttributes['password'] = $data['password'];
                $userAttributes['password_changed_at'] = now();
            }
            $profile->user->update($userAttributes);

            $profile->update([
                'company_name' => $data['company_name'] ?? null,
                'contact_person' => $data['contact_person'] ?? $data['name'],
                'phone' => $data['phone'] ?? null,
                'city' => $data['city'] ?? null,
                'gst_number' => $data['gst_number'] ?? null,
            ]);

            return $profile->fresh('user');
        });
    }

    /**
     * Activate / reject / suspend a vendor partner and notify them.
     */
    public function setStatus(VendorProfile $profile, VendorProfileStatus $status, User $actor, ?string $remarks = null): VendorProfile
    {
        $profile->update([
            'status' => $status->value,
            'activated_by' => $actor->id,
            'activated_at' => $status === VendorProfileStatus::Active ? now() : $profile->activated_at,
            'remarks' => $remarks ?? $profile->remarks,
        ]);

        $level = $status === VendorProfileStatus::Active ? NotificationLevel::Success : NotificationLevel::Warning;
        $this->notifications->notify($profile->user, 'vendor.'.$status->value, 'Vendor account '.$status->label(), [
            'level' => $level,
            'body' => $status === VendorProfileStatus::Active
                ? 'Your vendor account is active — you can now submit vehicles.'
                : 'Your vendor account status is now '.$status->label().'.',
            'action_url' => '/vendor',
        ]);

        return $profile->fresh();
    }
}
