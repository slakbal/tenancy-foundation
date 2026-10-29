<?php

use Slakbal\TenancyFoundation\Http\Responses\LogoutResponse;

test('logout response falls back to the root url without a configured route', function () {
    $response = (new LogoutResponse)->toResponse(request());

    expect($response->getTargetUrl())->toBe(url('/'));
});

test('install command runs and publishes the config', function () {
    $this->artisan('tenancy-foundation:install')->assertSuccessful();
});
