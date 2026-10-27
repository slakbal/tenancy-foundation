<?php

use Slakbal\TenancyFoundation\Models\Tenant;

beforeEach(function () {
    $this->travelTo('2026-10-28 12:00:00');
});

test('a tenant is cancelled once cancelled_at is set', function () {
    expect(Tenant::factory()->create()->isCancelled())->toBeFalse()
        ->and(Tenant::factory()->create(['cancelled_at' => now()])->isCancelled())->toBeTrue();
});

test('the lifecycle columns are cast to dates', function () {
    $tenant = Tenant::factory()->create(['cancelled_at' => '2026-10-01 08:00:00', 'scheduled_for_deletion_at' => '2026-10-31 23:59:59'])->fresh();

    expect($tenant->cancelled_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($tenant->scheduled_for_deletion_at->toDateString())->toBe('2026-10-31');
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
