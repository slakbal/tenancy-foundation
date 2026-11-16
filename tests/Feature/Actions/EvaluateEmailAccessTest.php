<?php

use Slakbal\TenancyFoundation\Actions\Auth\EvaluateEmailAccess;
use Slakbal\TenancyFoundation\Enums\AccessPolicy;
use Slakbal\TenancyFoundation\Enums\EmailAccess;
use Slakbal\TenancyFoundation\Models\DomainRule;
use Slakbal\TenancyFoundation\Models\Tenant;

test('an open tenant lets every domain in except blocked ones, ignoring allow rules', function () {
    $tenant = Tenant::factory()->create();
    DomainRule::factory()->create(['tenant_id' => $tenant->getKey(), 'domain' => ' Spam.Test ']);
    DomainRule::factory()->allow()->create(['tenant_id' => $tenant->getKey(), 'domain' => 'siv.de']);

    $evaluate = fn (string $email): EmailAccess => app(EvaluateEmailAccess::class)->handle($tenant, $email);

    expect($evaluate('jane@SPAM.test'))->toBe(EmailAccess::BLOCKED_BY_RULE)
        ->and($evaluate('jane@siv.de'))->toBe(EmailAccess::ALLOWED_BY_POLICY)
        ->and($evaluate('jane@example.test'))->toBe(EmailAccess::ALLOWED_BY_POLICY)
        ->and($evaluate('jane@example.test')->isAllowed())->toBeTrue();
});

test('an allow-list tenant lets in only allowed domains, ignoring block rules, and rules never cross tenants', function () {
    $tenant = Tenant::factory()->create(['access_policy' => AccessPolicy::ALLOW_LIST_ONLY]);
    DomainRule::factory()->allow()->create(['tenant_id' => $tenant->getKey(), 'domain' => 'siv.de']);
    DomainRule::factory()->create(['tenant_id' => $tenant->getKey(), 'domain' => 'blocked.test']);
    DomainRule::factory()->allow()->create(['domain' => 'other.test']);

    $evaluate = fn (string $email): EmailAccess => app(EvaluateEmailAccess::class)->handle($tenant, $email);

    expect($evaluate('s1@siv.de'))->toBe(EmailAccess::ALLOWED_BY_RULE)
        ->and($evaluate('x@blocked.test'))->toBe(EmailAccess::BLOCKED_BY_POLICY)
        ->and($evaluate('x@other.test'))->toBe(EmailAccess::BLOCKED_BY_POLICY)
        ->and($evaluate('x@other.test')->isAllowed())->toBeFalse();
});
