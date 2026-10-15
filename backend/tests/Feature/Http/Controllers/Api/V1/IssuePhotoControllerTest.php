<?php

use App\Models\Department;
use App\Models\Issue;
use App\Models\IssuePhoto;
use App\Models\Organization;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
});

function issueWithPhoto(Organization $organization): array
{
    $issue = Issue::factory()->create(['organization_id' => $organization->id]);
    $photo = IssuePhoto::factory()->create(['issue_id' => $issue->id]);
    Storage::disk('local')->put($photo->path, UploadedFile::fake()->image('p.jpg')->getContent());

    return [$issue, $photo];
}

it('shows an issue photo to someone who can see the issue', function () {
    $organization = Organization::factory()->create();
    [$issue, $photo] = issueWithPhoto($organization);

    $this->actingAs(userWithRole('technical-policy', $organization), 'sanctum')
        ->get("/api/v1/issues/{$issue->id}/photos/{$photo->id}")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

it('does not show an organization B issue photo to an organization A department-manager', function () {
    $organizationA = Organization::factory()->create();
    $departmentA = Department::factory()->create(['organization_id' => $organizationA->id]);
    [$issueB, $photoB] = issueWithPhoto(Organization::factory()->create());

    $this->actingAs(userWithRole('department-manager', $organizationA, $departmentA), 'sanctum')
        ->getJson("/api/v1/issues/{$issueB->id}/photos/{$photoB->id}")
        ->assertForbidden();
});

it('does not serve one issue\'s photo under another issue the user can see', function () {
    $organization = Organization::factory()->create();
    [$visibleIssue] = issueWithPhoto($organization);
    [, $otherPhoto] = issueWithPhoto(Organization::factory()->create());

    $this->actingAs(userWithRole('technical-policy', $organization), 'sanctum')
        ->getJson("/api/v1/issues/{$visibleIssue->id}/photos/{$otherPhoto->id}")
        ->assertNotFound();
});
