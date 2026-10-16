<?php

use App\Models\Setting;

it('tells even an outdated, logged-out app the minimum build and where to update', function () {
    Setting::set('mobile.min_build', 13, 'mobile');

    $response = $this->getJson('/api/v1/app/version', ['X-App-Build' => '5']);

    $response->assertOk()
        ->assertJsonPath('data.min_build', 13)
        ->assertJsonPath('data.store_url', config('mobile.store_url'));
});

it('reports 0 while no minimum is set', function () {
    $this->getJson('/api/v1/app/version')->assertOk()->assertJsonPath('data.min_build', 0);
});
