<?php

use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceUser;
use App\Models\Employee;
use App\Models\Organization;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->hr = userWithRole('hr', Organization::factory()->create());
});

function enroll(AttendanceDevice $device, string $userId, string $name): void
{
    AttendanceDeviceUser::factory()->create(['attendance_device_id' => $device->id, 'user_id' => $userId, 'name' => $name]);
}

it('compares terminal people with employees and suggests who an unlinked one may be', function () {
    $entry = AttendanceDevice::factory()->create(['device_id' => 'TURNSTILE-1']);
    $exit = AttendanceDevice::factory()->create(['device_id' => 'TURNSTILE-2']);
    $linked = Employee::factory()->create(['dahua_person_id' => '108', 'last_name' => 'Kenjayev', 'first_name' => 'Nodir']);
    $nodira = Employee::factory()->create(['last_name' => 'Matyoqubova', 'first_name' => 'Oydin']);
    $oybek = Employee::factory()->create(['last_name' => 'Matyoqubov', 'first_name' => 'Oybek']);
    $sardor = Employee::factory()->create(['last_name' => 'Matyoqubov', 'first_name' => 'Sardor']);
    $gone = Employee::factory()->create(['dahua_person_id' => '999']);
    enroll($entry, '108', 'Kenjayev N');
    enroll($exit, '108', 'Kenjayev N');
    enroll($entry, '28', 'MATYOQUBOV O.');

    $data = $this->actingAs($this->hr, 'sanctum')->getJson('/api/v1/attendance-devices/people')->assertOk()->json('data');

    expect($data['summary'])->toBe(['on_terminals' => 2, 'linked' => 1, 'unlinked' => 1, 'missing_on_terminals' => 1]);

    $people = collect($data['people'])->keyBy('user_id');
    expect($people['108']['devices'])->toBe(['TURNSTILE-1', 'TURNSTILE-2']);
    expect($people['108']['employee']['id'])->toBe($linked->id);
    expect($people['28']['employee'])->toBeNull();
    expect(collect($people['28']['suggestions'])->pluck('id')->all())->toBe([$oybek->id, $sardor->id], 'same surname, the matching initial first');
    expect($people['28']['suggestions'][0]['strong'])->toBeTrue();
    expect(collect($people['28']['suggestions'])->pluck('id'))->not->toContain($nodira->id);
    expect(collect($data['missing_on_terminals'])->pluck('id')->all())->toBe([$gone->id]);
});

it('lists terminal IDs in number order', function () {
    $device = AttendanceDevice::factory()->create();
    foreach (['10', '2', '100', '1'] as $userId) {
        enroll($device, $userId, "Person {$userId}");
    }

    $ids = collect($this->actingAs($this->hr, 'sanctum')->getJson('/api/v1/attendance-devices/people')->json('data.people'))->pluck('user_id');

    expect($ids->all())->toBe(['1', '2', '10', '100']);
});

it('matches surnames whatever apostrophe the terminal or the app used', function () {
    $device = AttendanceDevice::factory()->create();
    $employee = Employee::factory()->create(['last_name' => "Yo'ldoshev", 'first_name' => 'Anvar']);
    enroll($device, '5', 'Yoʻldoshev A');

    $people = $this->actingAs($this->hr, 'sanctum')->getJson('/api/v1/attendance-devices/people')->json('data.people');

    expect($people[0]['suggestions'][0]['id'])->toBe($employee->id);
});

it('reads every name shape the site\'s terminals use', function (string $terminalName, bool $strong) {
    $device = AttendanceDevice::factory()->create();
    $employee = Employee::factory()->create(['last_name' => 'Rahimov', 'first_name' => 'Nodir']);
    enroll($device, '5', $terminalName);

    $suggestion = $this->actingAs($this->hr, 'sanctum')->getJson('/api/v1/attendance-devices/people')->json('data.people.0.suggestions.0');

    expect($suggestion['id'])->toBe($employee->id);
    expect($suggestion['strong'])->toBe($strong);
})->with([
    'surname only' => ['Rahimov', false],
    'initial' => ['Rahimov N', true],
    'dotted initials' => ['Rahimov N.O.', true],
    'two capital initials' => ['Rahimov NO', true],
    'first name' => ['Rahimov Nodir', true],
    'lowercase first name' => ['rahimov nodir', true],
    'another first name' => ['Rahimov Sardor', false],
]);

it('links a terminal person to an employee so their scans count as that employee\'s', function () {
    $employee = Employee::factory()->create(['dahua_person_id' => null]);

    $this->actingAs($this->hr, 'sanctum')
        ->postJson('/api/v1/attendance-devices/people/link', ['user_id' => '28', 'employee_id' => $employee->id])
        ->assertOk();

    expect($employee->fresh()->dahua_person_id)->toBe('28');
    $this->assertDatabaseHas('audit_logs', ['module' => 'employees', 'entity_id' => $employee->id]);
});

it('never moves an ID silently', function () {
    $holder = Employee::factory()->create(['dahua_person_id' => '28']);
    $other = Employee::factory()->create(['dahua_person_id' => null]);
    $alreadyLinked = Employee::factory()->create(['dahua_person_id' => '30']);

    $this->actingAs($this->hr, 'sanctum')
        ->postJson('/api/v1/attendance-devices/people/link', ['user_id' => '28', 'employee_id' => $other->id])
        ->assertStatus(422)->assertJsonValidationErrors('user_id');
    $this->actingAs($this->hr, 'sanctum')
        ->postJson('/api/v1/attendance-devices/people/link', ['user_id' => '31', 'employee_id' => $alreadyLinked->id])
        ->assertStatus(422)->assertJsonValidationErrors('employee_id');

    expect($holder->fresh()->dahua_person_id)->toBe('28');
    expect($alreadyLinked->fresh()->dahua_person_id)->toBe('30');
});

it('unlinks an employee', function () {
    $employee = Employee::factory()->create(['dahua_person_id' => '28']);

    $this->actingAs($this->hr, 'sanctum')
        ->postJson('/api/v1/attendance-devices/people/unlink', ['employee_id' => $employee->id])
        ->assertOk();

    expect($employee->fresh()->dahua_person_id)->toBeNull();
});

it('keeps organization admins out of the comparison and linking', function () {
    $orgAdmin = userWithRole('organization-admin', Organization::factory()->create());
    $employee = Employee::factory()->create(['dahua_person_id' => null]);

    $this->actingAs($orgAdmin, 'sanctum')->getJson('/api/v1/attendance-devices/people')->assertForbidden();
    $this->actingAs($orgAdmin, 'sanctum')
        ->postJson('/api/v1/attendance-devices/people/link', ['user_id' => '28', 'employee_id' => $employee->id])
        ->assertForbidden();

    expect($employee->fresh()->dahua_person_id)->toBeNull();
});
