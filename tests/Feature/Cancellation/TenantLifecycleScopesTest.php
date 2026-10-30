<?php

use Slakbal\TenancyFoundation\Actions\Tenant\CancelTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Enums\TenantStatus;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;

beforeEach(function () {
    $this->travelTo('2026-10-28 12:00:00');
});

test('a tenant is only due for deletion once its scheduled moment has passed', function (?string $cancelled, ?string $scheduled, bool $due) {
    $tenant = Tenant::factory()->create(['cancelled_at' => $cancelled, 'scheduled_for_deletion_at' => $scheduled]);

    expect($tenant->isDueForDeletion())->toBe($due)
        ->and(Tenant::query()->dueForDeletion()->whereKey($tenant->getKey())->exists())->toBe($due);
})->with([
    'never cancelled' => [null, null, false],
    'scheduled tomorrow' => ['2026-10-01 08:00:00', '2026-10-29 23:59:59', false],
    'scheduled later today' => ['2026-10-01 08:00:00', '2026-10-28 23:59:59', false],
    'scheduled a minute ago' => ['2026-10-01 08:00:00', '2026-10-28 11:59:00', true],
    'scheduled last month' => ['2026-09-01 08:00:00', '2026-09-30 23:59:59', true],
    'scheduled without being cancelled' => [null, '2026-09-30 23:59:59', false],
]);

test('the active scope leaves cancelled tenants out', function () {
    $active = Tenant::factory()->create();
    Tenant::factory()->create(['cancelled_at' => now()]);

    expect(Tenant::query()->active()->pluck('id')->all())->toBe([$active->getKey()]);
});

test('owners lists only the owning members and status follows cancellation', function () {
    $owner = User::factory()->create();
    $tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $owner);
    $tenant->members()->attach(User::factory()->create(), ['is_owner' => false, 'is_default_tenant' => true]);

    expect($tenant->owners->pluck('id')->all())->toBe([$owner->getKey()])
        ->and($tenant->status)->toBe(TenantStatus::ACTIVE)
        ->and(app(CancelTenant::class)->handle($tenant)->status)->toBe(TenantStatus::CANCELLED);
});
