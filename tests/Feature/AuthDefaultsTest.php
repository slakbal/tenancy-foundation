<?php

use Illuminate\Support\Facades\File;
use Slakbal\TenancyFoundation\Http\Responses\LogoutResponse;

test('logout response falls back to the root url without a configured route', function () {
    $response = (new LogoutResponse)->toResponse(request());

    expect($response->getTargetUrl())->toBe(url('/'));
});

test('install command runs and publishes the config', function () {
    $configPath = sys_get_temp_dir().'/tenancy-foundation-auth-defaults-'.uniqid();
    File::ensureDirectoryExists($configPath);
    $this->app->useConfigPath($configPath);

    $this->artisan('tenancy-foundation:install', ['--no-interaction' => true])
        ->expectsOutputToContain('Published config/tenancy-foundation.php')
        ->assertSuccessful();

    expect(File::exists(config_path('tenancy-foundation.php')))->toBeTrue();

    File::deleteDirectory($configPath);
});
