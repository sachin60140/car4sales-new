<?php

use App\Domain\Settings\Models\Setting;

it('shows the settings page with the default booking terms to a permitted user', function () {
    $user = userWithPermissions(['settings.view', 'settings.update'], scope: 'all');

    $this->actingAs($user)
        ->get('/admin/settings')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('admin/settings/Index')
            ->where('canUpdate', true)
            ->has('bookingTerms')
            ->has('defaultBookingTerms'));
});

it('forbids the settings page without the settings permission', function () {
    $user = userWithPermissions(['dashboard.view'], scope: 'all');

    $this->actingAs($user)->get('/admin/settings')->assertForbidden();
});

it('saves an override for the booking terms', function () {
    $user = userWithPermissions(['settings.view', 'settings.update'], scope: 'all');

    $this->actingAs($user)
        ->patch('/admin/settings', ['booking_terms' => "First clause.\nSecond clause."])
        ->assertRedirect();

    expect(Setting::get('booking_terms'))->toBe("First clause.\nSecond clause.")
        ->and(Setting::bookingTerms())->toBe(['First clause.', 'Second clause.']);
});

it('falls back to the config default when the override is cleared', function () {
    $user = userWithPermissions(['settings.update'], scope: 'all');
    Setting::set('booking_terms', 'Something custom.');

    $this->actingAs($user)->patch('/admin/settings', ['booking_terms' => ''])->assertRedirect();

    expect(Setting::get('booking_terms'))->toBeNull()
        ->and(Setting::bookingTerms())->toBe(config('car4sales.booking_terms'));
});

it('forbids updating settings without the update permission', function () {
    $user = userWithPermissions(['settings.view'], scope: 'all');

    $this->actingAs($user)->patch('/admin/settings', ['booking_terms' => 'x'])->assertForbidden();
});
