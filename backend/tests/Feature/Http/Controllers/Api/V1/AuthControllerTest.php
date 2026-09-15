<?php

use App\Enums\UserStatus;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('logs in with an email identifier and returns a token', function () {
    $user = User::factory()->create(['password' => Hash::make('secret123')]);
    $user->assignRole('employee');

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => $user->email,
        'password' => 'secret123',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure(['data' => ['token', 'user']]);
});

it('logs in with a username identifier', function () {
    $user = User::factory()->create(['username' => 'jdoe', 'password' => Hash::make('secret123')]);
    $user->assignRole('employee');

    $this->postJson('/api/v1/auth/login', ['login' => 'jdoe', 'password' => 'secret123'])
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id);
});

it('logs in with an employee number identifier', function () {
    $user = User::factory()->create(['password' => Hash::make('secret123')]);
    $user->assignRole('employee');
    $organization = Organization::factory()->create();
    Employee::factory()->create([
        'user_id' => $user->id,
        'organization_id' => $organization->id,
        'employee_number' => 'EMP12345',
    ]);

    $this->postJson('/api/v1/auth/login', ['login' => 'EMP12345', 'password' => 'secret123'])
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id);
});

it('returns 401 for an unknown login identifier', function () {
    $this->postJson('/api/v1/auth/login', ['login' => 'nobody@urtg.uz', 'password' => 'whatever'])
        ->assertStatus(401)
        ->assertJsonPath('success', false);
});

it('returns 401 for an incorrect password', function () {
    $user = User::factory()->create(['password' => Hash::make('secret123')]);

    $this->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'wrong-password'])
        ->assertStatus(401);
});

it('records a login history row for both failed and successful attempts', function () {
    $user = User::factory()->create(['password' => Hash::make('secret123')]);
    $user->assignRole('employee');

    $this->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'wrong']);
    $this->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'secret123']);

    $this->assertDatabaseHas('login_histories', ['user_id' => $user->id, 'status' => 'failed']);
    $this->assertDatabaseHas('login_histories', ['user_id' => $user->id, 'status' => 'success']);
});

it('rejects login for an inactive account', function () {
    $user = User::factory()->create([
        'password' => Hash::make('secret123'),
        'status' => UserStatus::Inactive,
    ]);
    $user->assignRole('employee');

    $this->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'secret123'])
        ->assertStatus(403);
});

it('returns 401 when no token is provided for a protected route', function () {
    $this->getJson('/api/v1/auth/me')->assertStatus(401);
});

it('returns the authenticated user from the me endpoint', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('revokes the current token on logout', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');
    $token = $user->createToken('test')->plainTextToken;
    $tokenId = explode('|', $token)[0];

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
});

it('changes the password when the current password is correct', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);
    $user->assignRole('employee');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/change-password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertOk();

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

it('rejects a password change with the wrong current password', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);
    $user->assignRole('employee');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/change-password', [
            'current_password' => 'not-the-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');
});

it('sends a password reset link pointing at the frontend app', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
        ->assertOk()
        ->assertJsonPath('success', true);

    Notification::assertSentTo($user, ResetPassword::class);
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);
    $token = Password::broker()->createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertOk();

    expect(Hash::check('brand-new-password', $user->fresh()->password))->toBeTrue();
});

it('rejects an invalid password reset token', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertStatus(422)
        ->assertJsonPath('success', false);
});
