<?php

namespace App\Domain\Bookings\Policies;

use App\Domain\Bookings\Models\Booking;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Models\User;

class BookingPolicy
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function viewAny(User $user): bool
    {
        return $user->can('bookings.view');
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->can('bookings.view') && $this->inScope($user, $booking);
    }

    public function create(User $user): bool
    {
        return $user->can('bookings.create');
    }

    public function update(User $user, Booking $booking): bool
    {
        return $user->can('bookings.update') && $this->inScope($user, $booking);
    }

    public function approve(User $user, Booking $booking): bool
    {
        return $user->can('bookings.approve') && $this->inScope($user, $booking);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $user->can('bookings.cancel') && $this->inScope($user, $booking);
    }

    private function inScope(User $user, Booking $booking): bool
    {
        return $this->scopes->apply(Booking::query()->whereKey($booking->getKey()), $user, [
            'branch' => 'branch_id', 'assigned' => 'sales_executive_id', 'owner' => 'created_by',
        ])->exists()
            || $booking->sales_executive_id === $user->id
            || $booking->created_by === $user->id;
    }
}
