<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\DeleteTenant;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Events\TenantDeleting;
use Slakbal\TenancyFoundation\Models\Invitation;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Role;

function dueTenant(string $subdomain = 'acme', ?User $owner = null): Tenant
{
    $tenant = app(CreateTenant::class)->handle(['name' => ucfirst($subdomain), 'subdomain' => $subdomain], $owner ?? User::factory()->create());
    $tenant->forceFill(['cancelled_at' => now()->subDays(31), 'scheduled_for_deletion_at' => now()->subDay()->endOfDay()])->save();

    return $tenant->fresh();
}

test('a tenant that is not cancelled or not yet due cannot be deleted', function (Closure $makeTenant) {
    $tenant = $makeTenant();

    expect(fn () => app(DeleteTenant::class)->handle($tenant))->toThrow(LogicException::class);
    expect(Tenant::query()->whereKey($tenant->getKey())->exists())->toBeTrue();
})->with([
    'not cancelled' => [fn (): Tenant => Tenant::factory()->create()],
    'cancelled but before its scheduled moment' => [fn (): Tenant => Tenant::factory()->create(['cancelled_at' => now(), 'scheduled_for_deletion_at' => now()->addDays(10)])],
]);

test('a due tenant is deleted for good together with its memberships and the users who only belonged to it', function () {
    $owner = User::factory()->create();
    $tenant = dueTenant('acme', $owner);
    $member = User::factory()->create();
    $tenant->members()->attach($member, ['is_owner' => false, 'is_default_tenant' => true]);

    app(DeleteTenant::class)->handle($tenant);

    expect(Tenant::query()->whereKey($tenant->getKey())->exists())->toBeFalse()
        ->and(DB::table('tenant_user')->where('tenant_id', $tenant->getKey())->count())->toBe(0)
        ->and(User::withTrashed()->whereKey([$owner->getKey(), $member->getKey()])->count())->toBe(0);
});

test('soft deleted members of that tenant are removed too', function () {
    $tenant = dueTenant();
    $gone = User::factory()->create();
    $tenant->members()->attach($gone, ['is_owner' => false, 'is_default_tenant' => true]);
    $gone->delete();

    app(DeleteTenant::class)->handle($tenant);

    expect(User::withTrashed()->whereKey($gone->getKey())->exists())->toBeFalse();
});

test('users who also belong to another tenant keep their account', function () {
    $owner = User::factory()->create();
    $tenant = dueTenant('acme', $owner);
    $other = app(CreateTenant::class)->handle(['name' => 'Other', 'subdomain' => 'other'], User::factory()->create());
    $other->members()->attach($owner, ['is_owner' => false, 'is_default_tenant' => false]);

    app(DeleteTenant::class)->handle($tenant);

    expect(User::query()->whereKey($owner->getKey())->exists())->toBeTrue()
        ->and($owner->fresh()->tenants()->pluck('tenants.id')->all())->toBe([$other->getKey()]);
});

test('role assignments of that tenant are removed and those of other tenants stay', function () {
    $otherOwner = User::factory()->create();
    $other = app(CreateTenant::class)->handle(['name' => 'Other', 'subdomain' => 'other'], $otherOwner);
    $tenant = dueTenant();
    $team = config('permission.column_names.team_foreign_key');

    expect(DB::table('model_has_roles')->where($team, $tenant->getKey())->count())->toBe(1);

    app(DeleteTenant::class)->handle($tenant);

    expect(DB::table('model_has_roles')->where($team, $tenant->getKey())->count())->toBe(0)
        ->and(DB::table('model_has_roles')->where($team, $other->getKey())->count())->toBe(1)
        ->and(Tenant::query()->whereKey($other->getKey())->exists())->toBeTrue()
        ->and(User::query()->whereKey($otherOwner->getKey())->exists())->toBeTrue()
        ->and(DB::table('tenant_user')->where('tenant_id', $other->getKey())->count())->toBe(1)
        ->and(Role::query()->where('name', TenantRoles::OWNER->value)->where('guard_name', AuthGuard::TENANT->value)->exists())->toBeTrue();
});

test('the deleting event is dispatched while the tenant still exists so the app can clean up its own data', function () {
    $tenant = dueTenant();
    $seen = [];

    Event::listen(TenantDeleting::class, function (TenantDeleting $event) use (&$seen): void {
        $seen[] = Tenant::query()->whereKey($event->tenant->getKey())->exists();
    });

    app(DeleteTenant::class)->handle($tenant);

    expect($seen)->toBe([true]);
});

test('a failing cleanup listener rolls the whole deletion back', function () {
    $owner = User::factory()->create();
    $tenant = dueTenant('acme', $owner);

    Event::listen(TenantDeleting::class, fn () => throw new RuntimeException('cleanup failed'));

    expect(fn () => app(DeleteTenant::class)->handle($tenant))->toThrow(RuntimeException::class);

    expect(Tenant::query()->whereKey($tenant->getKey())->exists())->toBeTrue()
        ->and(User::query()->whereKey($owner->getKey())->exists())->toBeTrue()
        ->and(DB::table('model_has_roles')->where(config('permission.column_names.team_foreign_key'), $tenant->getKey())->count())->toBe(1);
});

test('deleting a tenant removes its invitations, accepted ones included, and keeps other tenants invitations', function () {
    $tenant = dueTenant();
    Invitation::factory()->create(['tenant_id' => $tenant->getKey()]);
    Invitation::factory()->create(['tenant_id' => $tenant->getKey(), 'accepted_at' => now()->subDay()]);
    $elsewhere = Invitation::factory()->create();

    app(DeleteTenant::class)->handle($tenant);

    expect(Invitation::query()->pluck('id')->all())->toBe([$elsewhere->getKey()]);
});
