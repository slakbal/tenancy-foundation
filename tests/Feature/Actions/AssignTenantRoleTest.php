<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);
    setPermissionsTeamId($this->tenant->getKey());

    $this->member = User::factory()->create();
    $this->tenant->members()->attach($this->member->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);
});

test('the permissions team id is restored even when assigning the role fails', function () {
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId(999);

    expect(fn () => app(AssignTenantRole::class)->handle(Tenant::factory()->create(), $this->tenant, TenantRoles::ADMIN))
        ->toThrow(BadMethodCallException::class);

    expect($registrar->getPermissionsTeamId())->toBe(999);
});

test('assigning a role replaces the given one inside the tenant only', function () {
    $second = Tenant::factory()->create();
    $second->members()->attach($this->owner->getKey(), ['is_owner' => true, 'is_default_tenant' => false]);
    app(AssignTenantRole::class)->handle($this->owner, $second, TenantRoles::OWNER);

    app(AssignTenantRole::class)->handle($this->owner, $this->tenant, TenantRoles::ADMIN, TenantRoles::OWNER);

    setPermissionsTeamId($this->tenant->getKey());
    expect($this->owner->fresh()->hasRole(TenantRoles::OWNER))->toBeFalse()
        ->and($this->owner->fresh()->hasRole(TenantRoles::ADMIN))->toBeTrue();

    setPermissionsTeamId($second->getKey());
    expect($this->owner->fresh()->hasRole(TenantRoles::OWNER))->toBeTrue();
});

test('assigning the same role twice does not duplicate it', function () {
    app(AssignTenantRole::class)->handle($this->member, $this->tenant, TenantRoles::ADMIN);
    app(AssignTenantRole::class)->handle($this->member, $this->tenant, TenantRoles::ADMIN);

    expect($this->member->roles()->where('name', TenantRoles::ADMIN->value)->count())->toBe(1);
});

test('roles are created once and stay global', function () {
    app(AssignTenantRole::class)->handle($this->member, $this->tenant, TenantRoles::ADMIN);

    $role = app(PermissionRegistrar::class)->getRoleClass()::where('name', TenantRoles::ADMIN->value)->get();

    expect($role)->toHaveCount(1)
        ->and($role->first()->getAttribute('tenant_id'))->toBeNull();
});
