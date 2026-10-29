<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\TransferTenantOwnership;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);

    $this->member = User::factory()->create();
    $this->tenant->members()->attach($this->member->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(config('tenancy-foundation.admin_permissions_team_id'));
});

test('an admin cannot delete a sole tenant owner but can delete other users', function (string $actor) {
    if ($actor === 'super admin') {
        Role::findOrCreate(AdminRoles::SUPER_ADMIN->value, AuthGuard::ADMIN->value);
        $admin = Admin::factory()->create();
        $admin->assignRole(AdminRoles::SUPER_ADMIN);
    } else {
        Permission::findOrCreate(AdminPermissions::USER_DELETE->value, AuthGuard::ADMIN->value);
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(AdminPermissions::USER_DELETE);
    }

    expect($admin->can('delete', $this->owner))->toBeFalse()
        ->and($admin->can('forceDelete', $this->owner))->toBeFalse()
        ->and($admin->can('delete', $this->member))->toBeTrue();
})->with(['super admin', 'admin with the delete permission']);

test('an admin can delete the former owner once ownership was transferred', function () {
    Role::findOrCreate(AdminRoles::SUPER_ADMIN->value, AuthGuard::ADMIN->value);
    $admin = Admin::factory()->create();
    $admin->assignRole(AdminRoles::SUPER_ADMIN);

    app(TransferTenantOwnership::class)->handle($this->tenant, $this->member, $this->owner);
    setPermissionsTeamId(config('tenancy-foundation.admin_permissions_team_id'));

    expect($admin->can('delete', $this->owner))->toBeTrue();
});
