<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Slakbal\TenancyFoundation\Actions\Tenant\CancelTenant;
use Slakbal\TenancyFoundation\Events\TenantCancelled;
use Slakbal\TenancyFoundation\Models\Invitation;
use Slakbal\TenancyFoundation\Models\Tenant;

beforeEach(function () {
    $this->travelTo('2026-10-28 09:30:00');
    config(['tenancy-foundation.cancellation.grace_days' => 30]);
});

test('cancelling marks the tenant as cancelled now', function () {
    $tenant = app(CancelTenant::class)->handle(Tenant::factory()->create());

    expect($tenant->cancelled_at->toDateTimeString())->toBe('2026-10-28 09:30:00')
        ->and($tenant->fresh()->isCancelled())->toBeTrue();
});

test('deletion is scheduled at the end of the day after the configured number of days', function (int|string $days, string $expected) {
    config(['tenancy-foundation.cancellation.grace_days' => $days]);

    $tenant = app(CancelTenant::class)->handle(Tenant::factory()->create());

    expect($tenant->scheduled_for_deletion_at->toDateTimeString())->toBe($expected);
})->with([
    'a month' => [30, '2026-11-27 23:59:59'],
    'same day' => [0, '2026-10-28 23:59:59'],
    'a week' => [7, '2026-11-04 23:59:59'],
    'as a string from the env' => ['14', '2026-11-11 23:59:59'],
]);

test('cancelling dispatches the event once after the commit', function () {
    Event::fake([TenantCancelled::class]);

    $tenant = Tenant::factory()->create();

    DB::transaction(function () use ($tenant): void {
        app(CancelTenant::class)->handle($tenant);

        Event::assertNotDispatched(TenantCancelled::class);
    });

    Event::assertDispatchedTimes(TenantCancelled::class, 1);
    Event::assertDispatched(TenantCancelled::class, fn (TenantCancelled $event): bool => $event->tenant->is($tenant));
});

test('cancelling twice keeps the first dates and does not dispatch again', function () {
    Event::fake([TenantCancelled::class]);
    $tenant = Tenant::factory()->create();

    app(CancelTenant::class)->handle($tenant);
    $this->travelTo('2026-11-05 10:00:00');
    $again = app(CancelTenant::class)->handle($tenant);

    expect($again->cancelled_at->toDateTimeString())->toBe('2026-10-28 09:30:00')
        ->and($again->scheduled_for_deletion_at->toDateTimeString())->toBe('2026-11-27 23:59:59');

    Event::assertDispatchedTimes(TenantCancelled::class, 1);
});

test('a stale instance is refreshed before it is cancelled', function () {
    $tenant = Tenant::factory()->create();
    Tenant::query()->whereKey($tenant->getKey())->update(['cancelled_at' => '2026-10-01 08:00:00', 'scheduled_for_deletion_at' => '2026-10-31 23:59:59']);

    $result = app(CancelTenant::class)->handle($tenant);

    expect($result->cancelled_at->toDateTimeString())->toBe('2026-10-01 08:00:00');
});

test('cancelling revokes the pending invitations of that tenant only', function () {
    $tenant = Tenant::factory()->create();
    $pending = Invitation::factory()->create(['tenant_id' => $tenant->getKey()]);
    $accepted = Invitation::factory()->create(['tenant_id' => $tenant->getKey(), 'accepted_at' => now()->subDay()]);
    $elsewhere = Invitation::factory()->create();

    app(CancelTenant::class)->handle($tenant);

    expect(Invitation::query()->whereKey($pending->getKey())->exists())->toBeFalse()
        ->and(Invitation::query()->whereKey([$accepted->getKey(), $elsewhere->getKey()])->count())->toBe(2);
});

test('the action itself leaves the invitations alone, the listener on the cancelled event revokes them', function () {
    Event::fake([TenantCancelled::class]);
    $tenant = Tenant::factory()->create();
    $pending = Invitation::factory()->create(['tenant_id' => $tenant->getKey()]);

    app(CancelTenant::class)->handle($tenant);

    expect(Invitation::query()->whereKey($pending->getKey())->exists())->toBeTrue();
});
