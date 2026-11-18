<?php

use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Auth\EvaluateEmailAccess;
use Slakbal\TenancyFoundation\Enums\AccessPolicy;
use Slakbal\TenancyFoundation\Enums\EmailAccess;
use Slakbal\TenancyFoundation\Models\DisposableDomain;
use Slakbal\TenancyFoundation\Models\DomainRule;
use Slakbal\TenancyFoundation\Models\Tenant;

function evaluateEmail(Model $tenant, string $email): EmailAccess
{
    return app(EvaluateEmailAccess::class)->handle($tenant, $email);
}

test('on an open tenant a block rule blocks its domain, an address allow rule beats it, and other emails pass', function () {
    $tenant = Tenant::factory()->create();
    DomainRule::factory()->create(['tenant_id' => $tenant->getKey(), 'value' => ' Gmail.COM ']);
    DomainRule::factory()->allow()->create(['tenant_id' => $tenant->getKey(), 'value' => 'Lead.Dev@gmail.com']);
    DomainRule::factory()->create(['tenant_id' => $tenant->getKey(), 'value' => 'spam@example.test']);

    expect(evaluateEmail($tenant, 'jane@GMAIL.com'))->toBe(EmailAccess::BLOCKED_BY_RULE)
        ->and(evaluateEmail($tenant, ' lead.dev@Gmail.com '))->toBe(EmailAccess::ALLOWED_BY_RULE)
        ->and(evaluateEmail($tenant, 'spam@example.test'))->toBe(EmailAccess::BLOCKED_BY_RULE)
        ->and(evaluateEmail($tenant, 'jane@example.test'))->toBe(EmailAccess::ALLOWED_BY_POLICY)
        ->and(evaluateEmail($tenant, 'jane@example.test')->isAllowed())->toBeTrue();
});

test('on an allow-list tenant only allowed domains and addresses pass, an address block beats its allowed domain, and rules never cross tenants', function () {
    $tenant = Tenant::factory()->create(['access_policy' => AccessPolicy::ALLOW_LIST_ONLY]);
    DomainRule::factory()->allow()->create(['tenant_id' => $tenant->getKey(), 'value' => 'siv.de']);
    DomainRule::factory()->create(['tenant_id' => $tenant->getKey(), 'value' => 'leaver@siv.de']);
    DomainRule::factory()->allow()->create(['tenant_id' => $tenant->getKey(), 'value' => 'guest@example.test']);
    DomainRule::factory()->allow()->create(['value' => 'other.test']);

    expect(evaluateEmail($tenant, 'S1@SIV.de'))->toBe(EmailAccess::ALLOWED_BY_RULE)
        ->and(evaluateEmail($tenant, 'leaver@siv.de'))->toBe(EmailAccess::BLOCKED_BY_RULE)
        ->and(evaluateEmail($tenant, 'guest@example.test'))->toBe(EmailAccess::ALLOWED_BY_RULE)
        ->and(evaluateEmail($tenant, 'someone@example.test'))->toBe(EmailAccess::BLOCKED_BY_POLICY)
        ->and(evaluateEmail($tenant, 'x@other.test'))->toBe(EmailAccess::BLOCKED_BY_POLICY)
        ->and(evaluateEmail($tenant, 'x@other.test')->isAllowed())->toBeFalse();
});

test('a disposable address is blocked before the policy decides, unless a rule allows it', function () {
    DisposableDomain::query()->create(['domain' => 'mailinator.com']);
    $tenant = Tenant::factory()->create();
    DomainRule::factory()->allow()->create(['tenant_id' => $tenant->getKey(), 'value' => 'tester@mailinator.com']);

    expect(evaluateEmail($tenant, 'jane@mailinator.com'))->toBe(EmailAccess::ALLOWED_BY_POLICY);

    config(['tenancy-foundation.features.disposable_emails' => true]);

    expect(evaluateEmail($tenant, 'jane@Mailinator.com'))->toBe(EmailAccess::BLOCKED_DISPOSABLE)
        ->and(evaluateEmail($tenant, 'jane@mailinator.com')->isAllowed())->toBeFalse()
        ->and(evaluateEmail($tenant, 'tester@mailinator.com'))->toBe(EmailAccess::ALLOWED_BY_RULE)
        ->and(evaluateEmail($tenant, 'jane@example.test'))->toBe(EmailAccess::ALLOWED_BY_POLICY);
});
