<?php

use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Policies\Tenant\UserPolicy;
use Slakbal\TenancyFoundation\Support\PolicyResolver;

test('falls back to the package policy when the application has none', function () {
    expect(PolicyResolver::resolve('Tenant', User::class))->toBe(UserPolicy::class);
});
