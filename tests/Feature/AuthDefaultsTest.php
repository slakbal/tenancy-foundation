<?php

use Illuminate\Support\Facades\File;
use Slakbal\TenancyFoundation\Http\Responses\LogoutResponse;

test('logout response falls back to the root url without a configured route', function () {
    $response = (new LogoutResponse)->toResponse(request());

    expect($response->getTargetUrl())->toBe(url('/'));
});

test('install command runs and publishes the config', function () {
    $published = config_path('tenancy-foundation.php');
    $existed = File::exists($published);

    $this->artisan('tenancy-foundation:install')
        ->expectsOutputToContain('Published config/tenancy-foundation.php')
        ->assertSuccessful();

    expect(File::exists($published))->toBeTrue();

    if (! $existed) {
        File::delete($published);
    }
});
