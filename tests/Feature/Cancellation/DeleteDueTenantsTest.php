<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Slakbal\TenancyFoundation\Actions\DeleteTenant;
use Slakbal\TenancyFoundation\Events\TenantDeleting;
use Slakbal\TenancyFoundation\Jobs\DeleteDueTenants;
use Slakbal\TenancyFoundation\Models\Tenant;

function cancelledTenant(string $subdomain, string $scheduled): Tenant
{
    return Tenant::factory()->create(['subdomain' => $subdomain, 'cancelled_at' => now()->subDays(40), 'scheduled_for_deletion_at' => $scheduled]);
}

test('the job deletes tenants whose deletion moment has passed and nothing else', function () {
    $this->travelTo('2026-10-28 01:00:00');
    $due = cancelledTenant('due', '2026-10-27 23:59:59');
    $later = cancelledTenant('later', '2026-11-05 23:59:59');
    $active = Tenant::factory()->create(['subdomain' => 'active']);

    (new DeleteDueTenants)->handle(app(DeleteTenant::class));

    expect(Tenant::query()->pluck('subdomain')->sort()->values()->all())->toBe(['active', 'later'])
        ->and(Tenant::query()->whereKey($due->getKey())->exists())->toBeFalse()
        ->and(Tenant::query()->whereKey([$later->getKey(), $active->getKey()])->count())->toBe(2);
});

test('the job keeps going when one tenant fails to delete', function () {
    $this->travelTo('2026-10-28 01:00:00');
    $broken = cancelledTenant('broken', '2026-10-20 23:59:59');
    $fine = cancelledTenant('fine', '2026-10-21 23:59:59');

    Event::listen(TenantDeleting::class, function (TenantDeleting $event): void {
        throw_if($event->tenant->subdomain === 'broken', RuntimeException::class, 'cannot delete');
    });

    (new DeleteDueTenants)->handle(app(DeleteTenant::class));

    expect(Tenant::query()->whereKey($broken->getKey())->exists())->toBeTrue()
        ->and(Tenant::query()->whereKey($fine->getKey())->exists())->toBeFalse();
});

test('a tenant that failed is picked up again on the next run', function () {
    $this->travelTo('2026-10-28 01:00:00');
    $tenant = cancelledTenant('retry', '2026-10-20 23:59:59');
    $fail = true;

    Event::listen(TenantDeleting::class, function () use (&$fail): void {
        throw_if($fail, RuntimeException::class, 'not yet');
    });

    (new DeleteDueTenants)->handle(app(DeleteTenant::class));
    expect(Tenant::query()->whereKey($tenant->getKey())->exists())->toBeTrue();

    $fail = false;
    (new DeleteDueTenants)->handle(app(DeleteTenant::class));

    expect(Tenant::query()->whereKey($tenant->getKey())->exists())->toBeFalse();
});

test('the job runs without anything due', function () {
    Tenant::factory()->create();

    (new DeleteDueTenants)->handle(app(DeleteTenant::class));

    expect(Tenant::query()->count())->toBe(1);
});

test('the deletion job is scheduled daily at the configured time', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->description, DeleteDueTenants::class));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 1 * * *')
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->withoutOverlapping)->toBeTrue();
});
