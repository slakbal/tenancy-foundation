<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Slakbal\TenancyFoundation\Actions\Tenant\DeleteTenant;
use Slakbal\TenancyFoundation\Events\TenantDeleting;
use Slakbal\TenancyFoundation\Jobs\DeleteDueTenants;
use Slakbal\TenancyFoundation\Models\Tenant;

function cancelledTenant(string $subdomain, string $scheduled): Tenant
{
    return Tenant::factory()->create(['subdomain' => $subdomain, 'cancelled_at' => now()->subDays(40), 'scheduled_for_deletion_at' => $scheduled]);
}

test('the job deletes tenants whose deletion moment has passed and nothing else', function () {
    $this->travelTo('2026-10-28 01:00:00');
    cancelledTenant('due', '2026-10-27 23:59:59');
    cancelledTenant('later', '2026-11-05 23:59:59');
    Tenant::factory()->create(['subdomain' => 'active']);

    (new DeleteDueTenants)->handle(app(DeleteTenant::class));

    expect(Tenant::query()->pluck('subdomain')->sort()->values()->all())->toBe(['active', 'later']);
});

test('the job keeps going when one tenant fails to delete and picks it up again on the next run', function () {
    $this->travelTo('2026-10-28 01:00:00');
    $broken = cancelledTenant('broken', '2026-10-20 23:59:59');
    $fine = cancelledTenant('fine', '2026-10-21 23:59:59');
    $fail = true;

    Event::listen(TenantDeleting::class, function (TenantDeleting $event) use (&$fail): void {
        throw_if($fail && $event->tenant->subdomain === 'broken', RuntimeException::class, 'cannot delete');
    });

    (new DeleteDueTenants)->handle(app(DeleteTenant::class));

    expect(Tenant::query()->whereKey($broken->getKey())->exists())->toBeTrue()
        ->and(Tenant::query()->whereKey($fine->getKey())->exists())->toBeFalse();

    $fail = false;
    (new DeleteDueTenants)->handle(app(DeleteTenant::class));

    expect(Tenant::query()->whereKey($broken->getKey())->exists())->toBeFalse();
});

function deletionScheduleEvent(): ?object
{
    app()->forgetInstance(Schedule::class);

    return collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->description, DeleteDueTenants::class));
}

test('the deletion job is scheduled daily at the configured time', function (string $time, string $expression) {
    config(['tenancy-foundation.cancellation.deletion_time' => $time]);

    $event = deletionScheduleEvent();

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe($expression)
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->withoutOverlapping)->toBeTrue();
})->with([
    'default' => ['01:00', '0 1 * * *'],
    'configured' => ['03:30', '30 3 * * *'],
]);

test('the deletion job is not scheduled when switched off', function () {
    config(['tenancy-foundation.cancellation.schedule_deletion' => false]);

    expect(deletionScheduleEvent())->toBeNull();
});
