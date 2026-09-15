<?php

use App\Models\EmployeeDocument;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\DocumentApproved;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when listing notifications without authentication', function () {
    $this->getJson('/api/v1/notifications')->assertStatus(401);
});

it('lists only the authenticated user own notifications', function () {
    $user = userWithRole('employee', Organization::factory()->create());
    $otherUser = User::factory()->create();
    $document = EmployeeDocument::factory()->create();

    $user->notify(new DocumentApproved($document));
    $otherUser->notify(new DocumentApproved($document));

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/notifications');

    $response->assertOk()->assertJsonPath('meta.total', 1);
});

it('reports the unread count', function () {
    $user = userWithRole('employee', Organization::factory()->create());
    $document = EmployeeDocument::factory()->create();
    $user->notify(new DocumentApproved($document));
    $user->notify(new DocumentApproved($document));

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.count', 2);
});

it('marks a single notification as read', function () {
    $user = userWithRole('employee', Organization::factory()->create());
    $document = EmployeeDocument::factory()->create();
    $user->notify(new DocumentApproved($document));
    $notification = $user->notifications()->first();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('data.read_at', fn ($value) => $value !== null);
});

it('forbids marking another user notification as read', function () {
    $user = userWithRole('employee', Organization::factory()->create());
    $otherUser = User::factory()->create();
    $document = EmployeeDocument::factory()->create();
    $otherUser->notify(new DocumentApproved($document));
    $notification = $otherUser->notifications()->first();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertStatus(403);
});

it('marks every notification as read', function () {
    $user = userWithRole('employee', Organization::factory()->create());
    $document = EmployeeDocument::factory()->create();
    $user->notify(new DocumentApproved($document));
    $user->notify(new DocumentApproved($document));

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/notifications/read-all')
        ->assertOk();

    expect($user->unreadNotifications()->count())->toBe(0);
});
