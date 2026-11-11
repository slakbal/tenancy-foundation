<?php

use Slakbal\TenancyFoundation\Enums\Authorisation\TenantUserAbilities;
use Slakbal\TenancyFoundation\Policies\Tenant\UserPolicy;

test('every tenant user ability has a matching public method on the tenant user policy', function (TenantUserAbilities $ability) {
    expect(method_exists(UserPolicy::class, $ability->value))->toBeTrue()
        ->and((new ReflectionMethod(UserPolicy::class, $ability->value))->isPublic())->toBeTrue();
})->with(TenantUserAbilities::cases());
