<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Actions\Tenant\CancelTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\DeleteTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\ReactivateTenant;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Events\Tenant\TenantCancelled;
use Slakbal\TenancyFoundation\Events\Tenant\TenantDeleting;
use Slakbal\TenancyFoundation\Jobs\DeleteDueTenants;
use Slakbal\TenancyFoundation\Models\Invitation;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Role;

function teamRows(string $table, Tenant $tenant): int
{
    return DB::table(config("permission.table_names.{$table}"))->where(config('permission.column_names.team_foreign_key'), $tenant->getKey())->count();
}

function memberWithRole(Tenant $tenant, TenantRoles $role, bool $default = true): User
{
    $user = User::factory()->create();
    $tenant->members()->attach($user, ['is_owner' => false, 'is_default_tenant' => $default]);
    app(AssignTenantRole::class)->handle($user, $tenant, $role);

    return $user;
}

beforeEach(function () {
    $this->travelTo('2026-10-01 09:00:00');
    config(['tenancy-foundation.cancellation.grace_days' => 30]);
    $this->seen = [];
    Event::listen(TenantCancelled::class, fn (TenantCancelled $event) => $this->seen[] = 'cancelled:'.$event->tenant->subdomain);
    Event::listen(TenantDeleting::class, fn (TenantDeleting $event) => $this->seen[] = 'deleting:'.$event->tenant->subdomain);

    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);
    $this->admin = memberWithRole($this->tenant, TenantRoles::ADMIN);
    $this->viewer = memberWithRole($this->tenant, TenantRoles::VIEW_ONLY);
    $this->removedViewer = memberWithRole($this->tenant, TenantRoles::VIEW_ONLY);
    $this->removedViewer->delete();

    $this->otherOwner = User::factory()->create();
    $this->other = app(CreateTenant::class)->handle(['name' => 'Other', 'subdomain' => 'other'], $this->otherOwner);
    $this->shared = memberWithRole($this->tenant, TenantRoles::ADMIN);
    $this->other->members()->attach($this->shared, ['is_owner' => false, 'is_default_tenant' => false]);
    app(AssignTenantRole::class)->handle($this->shared, $this->other, TenantRoles::VIEW_ONLY);

    $this->pending = Invitation::factory()->create(['tenant_id' => $this->tenant->getKey()]);
    $this->accepted = Invitation::factory()->create(['tenant_id' => $this->tenant->getKey(), 'accepted_at' => now()->subDay()]);
    $this->otherPending = Invitation::factory()->create(['tenant_id' => $this->other->getKey()]);
});

test('cancelling revokes pending invitations and keeps every member, role and the other tenant untouched', function () {
    app(CancelTenant::class)->handle($this->tenant);

    expect($this->tenant->fresh()->isCancelled())->toBeTrue()
        ->and($this->tenant->fresh()->scheduled_for_deletion_at->toDateString())->toBe('2026-10-31')
        ->and(Invitation::query()->whereKey($this->pending->getKey())->exists())->toBeFalse()
        ->and(Invitation::query()->whereKey([$this->accepted->getKey(), $this->otherPending->getKey()])->count())->toBe(2)
        ->and($this->tenant->members()->withTrashed()->count())->toBe(5)
        ->and(teamRows('model_has_roles', $this->tenant))->toBe(5)
        ->and($this->other->fresh()->isCancelled())->toBeFalse()
        ->and($this->seen)->toBe(['cancelled:acme']);
});

test('the scheduled deletion removes the tenant with its data, deletes users who only belonged to it and keeps the rest', function () {
    app(CancelTenant::class)->handle($this->tenant);

    (new DeleteDueTenants)->handle(app(DeleteTenant::class));
    expect(Tenant::query()->whereKey($this->tenant->getKey())->exists())->toBeTrue();

    $this->travelTo('2026-11-01 01:00:00');
    $this->seen = [];
    (new DeleteDueTenants)->handle(app(DeleteTenant::class));

    expect(Tenant::query()->whereKey($this->tenant->getKey())->exists())->toBeFalse()
        ->and($this->seen)->toBe(['deleting:acme'])
        ->and(Invitation::query()->where('tenant_id', $this->tenant->getKey())->count())->toBe(0)
        ->and(DB::table('tenant_user')->where('tenant_id', $this->tenant->getKey())->count())->toBe(0)
        ->and(teamRows('model_has_roles', $this->tenant))->toBe(0)
        ->and(teamRows('model_has_permissions', $this->tenant))->toBe(0)
        ->and(teamRows('roles', $this->tenant))->toBe(0)
        ->and(User::withTrashed()->whereKey([$this->owner->getKey(), $this->admin->getKey(), $this->viewer->getKey(), $this->removedViewer->getKey()])->count())->toBe(0);

    $shared = User::query()->find($this->shared->getKey());
    setPermissionsTeamId($this->other->getKey());

    expect($shared)->not->toBeNull()
        ->and($shared->tenants()->pluck('tenants.id')->all())->toBe([$this->other->getKey()])
        ->and($shared->fresh()->hasRole(TenantRoles::VIEW_ONLY))->toBeTrue()
        ->and(User::query()->whereKey($this->otherOwner->getKey())->exists())->toBeTrue()
        ->and(Tenant::query()->whereKey($this->other->getKey())->exists())->toBeTrue()
        ->and(Invitation::query()->whereKey($this->otherPending->getKey())->exists())->toBeTrue()
        ->and(teamRows('model_has_roles', $this->other))->toBe(2)
        ->and(Role::query()->where('guard_name', AuthGuard::TENANT->value)->whereNull(config('permission.column_names.team_foreign_key'))->exists())->toBeTrue();
});

test('reactivating before the deadline keeps everything and a later deletion finds nothing due', function () {
    app(CancelTenant::class)->handle($this->tenant);
    app(ReactivateTenant::class)->handle($this->tenant->fresh());

    $this->travelTo('2026-11-05 01:00:00');
    (new DeleteDueTenants)->handle(app(DeleteTenant::class));

    expect(Tenant::query()->whereKey($this->tenant->getKey())->exists())->toBeTrue()
        ->and(User::query()->whereKey($this->owner->getKey())->exists())->toBeTrue()
        ->and(teamRows('model_has_roles', $this->tenant))->toBe(5);
});
