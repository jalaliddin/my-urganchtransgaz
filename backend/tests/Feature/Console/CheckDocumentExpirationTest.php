<?php

use App\Enums\DocumentStatus;
use App\Models\EmployeeDocument;
use App\Models\Organization;
use App\Notifications\DocumentExpiring;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('notifies the employee at each expiry threshold', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    $document = EmployeeDocument::factory()->create([
        'employee_id' => $user->employee->id,
        'status' => DocumentStatus::Approved->value,
        'expiry_date' => Carbon::today()->addDays(7),
    ]);

    $this->artisan('documents:check-expiration')->assertExitCode(0);

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $user->id,
        'type' => DocumentExpiring::class,
    ]);

    $notification = $user->notifications()->first();
    expect($notification->data['threshold'])->toBe(7)
        ->and($notification->data['document_id'])->toBe($document->id);
});

it('notifies once for an already-expired document', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    EmployeeDocument::factory()->create([
        'employee_id' => $user->employee->id,
        'status' => DocumentStatus::Approved->value,
        'expiry_date' => Carbon::today()->subDays(3),
    ]);

    $this->artisan('documents:check-expiration');

    $notification = $user->notifications()->first();
    expect($notification->data['threshold'])->toBe('expired');
});

it('does not send a duplicate notification for the same document and threshold', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    EmployeeDocument::factory()->create([
        'employee_id' => $user->employee->id,
        'status' => DocumentStatus::Approved->value,
        'expiry_date' => Carbon::today()->addDays(30),
    ]);

    $this->artisan('documents:check-expiration');
    $this->artisan('documents:check-expiration');

    expect($user->notifications()->count())->toBe(1);
});

it('does not notify for a pending (not yet approved) document', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    EmployeeDocument::factory()->create([
        'employee_id' => $user->employee->id,
        'status' => DocumentStatus::Pending->value,
        'expiry_date' => Carbon::today()->addDays(1),
    ]);

    $this->artisan('documents:check-expiration');

    expect($user->notifications()->count())->toBe(0);
});

it('does not notify for a document that is not near any threshold', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    EmployeeDocument::factory()->create([
        'employee_id' => $user->employee->id,
        'status' => DocumentStatus::Approved->value,
        'expiry_date' => Carbon::today()->addDays(15),
    ]);

    $this->artisan('documents:check-expiration');

    expect($user->notifications()->count())->toBe(0);
});
