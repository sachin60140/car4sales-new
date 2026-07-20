<?php

use App\Models\User;

it('logs in with valid credentials and device registration', function () {
    $user = userWithPermissions(['access-mobile']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Pixel 8',
        'device_uuid' => 'test-device-uuid',
        'platform' => 'android',
        'fcm_token' => 'fcm-abc',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.device_uuid', 'test-device-uuid')
        ->assertJsonStructure(['success', 'message', 'data' => ['token', 'user' => ['id', 'permissions']], 'meta']);

    $this->assertDatabaseHas('user_devices', [
        'user_id' => $user->id,
        'device_uuid' => 'test-device-uuid',
        'fcm_token' => 'fcm-abc',
    ]);

    $this->assertDatabaseHas('login_histories', [
        'user_id' => $user->id,
        'guard' => 'api',
        'event' => 'login',
    ]);
});

it('rejects invalid credentials with the error envelope', function () {
    $user = userWithPermissions(['access-mobile']);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'Pixel 8',
    ])
        ->assertStatus(401)
        ->assertJsonPath('success', false);

    $this->assertDatabaseHas('login_histories', [
        'user_id' => $user->id,
        'event' => 'failed',
        'guard' => 'api',
    ]);
});

it('blocks deactivated accounts from the api', function () {
    $user = userWithPermissions(['access-mobile'], attributes: ['is_active' => false]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ])->assertStatus(403);
});

it('requires the access-mobile permission', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ])
        ->assertStatus(403)
        ->assertJsonPath('message', 'You are not allowed to use the mobile application.');
});

it('returns the profile with roles and permissions from /auth/me', function () {
    $user = userWithPermissions(['access-mobile', 'purchase-leads.view']);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ])->json('data.token');

    $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$login}"])
        ->assertOk()
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonFragment(['permissions' => ['access-mobile', 'purchase-leads.view']]);
});

it('rejects unauthenticated api requests with the envelope', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('success', false);
});

it('logs out and revokes the device token', function () {
    $user = userWithPermissions(['access-mobile']);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Pixel 8',
        'device_uuid' => 'dev-1',
    ])->json('data.token');

    $this->postJson('/api/v1/auth/logout', [], ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($user->tokens()->count())->toBe(0)
        ->and($user->devices()->whereNotNull('revoked_at')->count())->toBe(1);
});

it('updates the push token for a registered device', function () {
    $user = userWithPermissions(['access-mobile']);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Pixel 8',
        'device_uuid' => 'dev-9',
    ])->json('data.token');

    $this->postJson('/api/v1/auth/device/push-token', [
        'device_uuid' => 'dev-9',
        'fcm_token' => 'new-fcm-token',
    ], ['Authorization' => "Bearer {$token}"])
        ->assertOk();

    $this->assertDatabaseHas('user_devices', [
        'user_id' => $user->id,
        'device_uuid' => 'dev-9',
        'fcm_token' => 'new-fcm-token',
    ]);
});

it('serves the dashboard widgets according to permissions', function () {
    $user = userWithPermissions(['access-mobile', 'branches.view']);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ])->json('data.token');

    $this->getJson('/api/v1/dashboard', ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['widgets' => ['branches', 'departments', 'teams']]]);
});
