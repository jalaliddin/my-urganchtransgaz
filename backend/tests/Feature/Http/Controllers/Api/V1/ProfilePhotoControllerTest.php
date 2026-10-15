<?php

use App\Models\Organization;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
});

it('takes a full-size phone photo and stores it cut down to 512 px', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($user, 'sanctum')->post('/api/v1/profile/photo', [
        'photo' => UploadedFile::fake()->image('camera.jpg', 3000, 4000)->size(6000),
    ], ['Accept' => 'application/json'])->assertOk();

    $path = $user->employee->fresh()->photo;
    expect(getimagesizefromstring(Storage::disk('local')->get($path)))->toMatchArray([0 => 512, 1 => 512]);
});

it('refuses a photo over 10 MB', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($user, 'sanctum')->post('/api/v1/profile/photo', [
        'photo' => UploadedFile::fake()->image('huge.jpg', 100, 100)->size(10241),
    ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('photo');
});
