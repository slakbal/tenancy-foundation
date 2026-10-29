<?php

use Illuminate\Support\Facades\Event;
use Slakbal\TenancyFoundation\Actions\Tenant\CancelTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\ReactivateTenant;
use Slakbal\TenancyFoundation\Enums\TenantStatus;
use Slakbal\TenancyFoundation\Events\TenantReactivated;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;

beforeEach(function () {
    $this->travelTo('2026-10-28 09:30:00');
    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);
});

test('reactivating clears both cancellation dates', function () {
    app(CancelTenant::class)->handle($this->tenant);

    $tenant = app(ReactivateTenant::class)->handle($this->tenant);

    expect($tenant->cancelled_at)->toBeNull()
        ->and($tenant->scheduled_for_deletion_at)->toBeNull()
        ->and($tenant->fresh()->isCancelled())->toBeFalse()
        ->and($tenant->fresh()->status)->toBe(TenantStatus::ACTIVE);
});

test('reactivating dispatches the event once', function () {
    app(CancelTenant::class)->handle($this->tenant);
    Event::fake([TenantReactivated::class]);

    app(ReactivateTenant::class)->handle($this->tenant);

    Event::assertDispatchedTimes(TenantReactivated::class, 1);
});

test('reactivating a tenant that is not cancelled does nothing and dispatches nothing', function () {
    Event::fake([TenantReactivated::class]);

    $tenant = app(ReactivateTenant::class)->handle($this->tenant);

    expect($tenant->isCancelled())->toBeFalse();
    Event::assertNotDispatched(TenantReactivated::class);
});

test('a tenant that is already past its deletion moment can still be reactivated until it is deleted', function () {
    $this->tenant->forceFill(['cancelled_at' => now()->subDays(40), 'scheduled_for_deletion_at' => now()->subDay()])->save();

    app(ReactivateTenant::class)->handle($this->tenant);

    expect($this->tenant->fresh()->isDueForDeletion())->toBeFalse()
        ->and(Tenant::query()->dueForDeletion()->count())->toBe(0);
});

test('a stale instance is refreshed before it is reactivated', function () {
    app(CancelTenant::class)->handle($this->tenant);
    $stale = Tenant::query()->find($this->tenant->getKey());
    $stale->setRawAttributes([...$stale->getAttributes(), 'cancelled_at' => null]);

    $tenant = app(ReactivateTenant::class)->handle($stale);

    expect($tenant->fresh()->isCancelled())->toBeFalse();
});

test('reactivating gives the users their access back', function () {
    app(CancelTenant::class)->handle($this->tenant);
    expect($this->owner->fresh()->hasOnlyCancelledTenants())->toBeTrue();

    app(ReactivateTenant::class)->handle($this->tenant);

    expect($this->owner->fresh()->hasOnlyCancelledTenants())->toBeFalse();
});

test('a tenant can be cancelled again after it was reactivated with fresh dates', function () {
    config(['tenancy-foundation.cancellation.grace_days' => 30]);
    app(CancelTenant::class)->handle($this->tenant);
    app(ReactivateTenant::class)->handle($this->tenant);

    $this->travelTo('2026-12-01 10:00:00');
    $tenant = app(CancelTenant::class)->handle($this->tenant);

    expect($tenant->cancelled_at->toDateTimeString())->toBe('2026-12-01 10:00:00')
        ->and($tenant->scheduled_for_deletion_at->toDateTimeString())->toBe('2026-12-31 23:59:59');
});

test('the status and owners helpers describe the tenant', function () {
    $member = User::factory()->create();
    $this->tenant->members()->attach($member, ['is_owner' => false, 'is_default_tenant' => true]);

    expect($this->tenant->owners->pluck('id')->all())->toBe([$this->owner->getKey()])
        ->and(app(CancelTenant::class)->handle($this->tenant)->status)->toBe(TenantStatus::CANCELLED);
});
