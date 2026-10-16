<?php

use App\Models\Setting;
use App\Models\User;

const OLD_APP_USER_AGENT = 'Dart/3.9 (dart:io)';

it('answers an app build below the minimum with 426 and how to update', function () {
    Setting::set('mobile.min_build', 13, 'mobile');
    Setting::set('mobile.store_url', 'https://play.google.com/store/apps/details?id=uz.urtg.urtg_mobile', 'mobile');

    $response = $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/auth/me', ['X-App-Build' => '12']);

    $response->assertStatus(426)
        ->assertJsonPath('code', 'app_update_required')
        ->assertJsonPath('message', 'Ilovaning yangi versiyasi chiqdi. Ishni davom ettirish uchun ilovani yangilang.')
        ->assertJsonPath('min_build', 13)
        ->assertJsonPath('store_url', 'https://play.google.com/store/apps/details?id=uz.urtg.urtg_mobile');
});

it('lets the minimum build and newer ones through', function () {
    Setting::set('mobile.min_build', 13, 'mobile');
    $user = User::factory()->create();

    foreach (['13', '14'] as $build) {
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me', ['X-App-Build' => $build])->assertOk();
    }
});

it('treats an app that sends no build number as outdated, so builds before the check also have to update', function () {
    Setting::set('mobile.min_build', 13, 'mobile');

    $this->postJson('/api/v1/auth/login', ['login' => 'someone', 'password' => 'secret'], ['User-Agent' => OLD_APP_USER_AGENT])
        ->assertStatus(426)
        ->assertJsonPath('code', 'app_update_required');
});

it('never blocks the web app', function () {
    Setting::set('mobile.min_build', 13, 'mobile');

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/auth/me', ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Chrome/141.0'])
        ->assertOk();
});

it('blocks nothing while the minimum build is 0', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/auth/me', ['User-Agent' => OLD_APP_USER_AGENT])
        ->assertOk();
});

it('falls back to the config value when no admin has set a minimum', function () {
    config(['mobile.min_build' => 20]);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/auth/me', ['X-App-Build' => '19'])
        ->assertStatus(426);
});

it('leaves attendance device webhooks alone', function () {
    Setting::set('mobile.min_build', 13, 'mobile');

    $this->postJson('/api/v1/integrations/attendance/events', [], ['User-Agent' => OLD_APP_USER_AGENT])
        ->assertUnauthorized();
});
