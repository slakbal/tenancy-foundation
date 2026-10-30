<?php

use Slakbal\TenancyFoundation\Models\Admin;
use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\TransferTenantOwnership;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);

    $this->member = User::factory()->create();
    $this->tenant->members()->attach($this->member->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);
});

test('an admin cannot delete a sole tenant owner but can delete other users', function (array $grants) {
    $admin = adminWithAccess(...$grants);

    expect($admin->can('delete', $this->owner))->toBeFalse()
        ->and($admin->can('forceDelete', $this->owner))->toBeFalse()
        ->and($admin->can('delete', $this->member))->toBeTrue()
        ->and($admin->can('forceDelete', $this->member))->toBeTrue();
})->with([
    'super admin' => [[AdminRoles::SUPER_ADMIN]],
    'admin with the delete permissions' => [[AdminPermissions::USER_DELETE, AdminPermissions::USER_FORCE_DELETE]],
]);

test('an admin can delete the former owner once ownership was transferred', function () {
    $admin = adminWithAccess(AdminRoles::SUPER_ADMIN);

    app(TransferTenantOwnership::class)->handle($this->tenant, $this->member, $this->owner);
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

    expect($admin->can('delete', $this->owner))->toBeTrue();
});
