<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Roles\ApplyPermissionMatrix;
use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Database\Seeders\TenantRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Filament\Admin\Pages\ManageRolePermissions;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\RolePermissionChange;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function adminRole(AdminRoles $role): Role
{
    return Role::findByName($role->value, AuthGuard::ADMIN->value);
}

function adminPermissionId(AdminPermissions $permission): int
{
    return Permission::findByName($permission->value, AuthGuard::ADMIN->value)->getKey();
}

function openPermissionsPage(?Admin $admin = null)
{
    test()->actingAs($admin ?? adminWithAccess(AdminRoles::SUPER_ADMIN), AuthGuard::ADMIN->value);

    return Livewire::test(ManageRolePermissions::class);
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel(FilamentPanel::ADMIN->getPanelId()));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

    $this->seed(AdminRolePermissionsSeeder::class);
    $this->seed(TenantRolePermissionsSeeder::class);
});

describe('access', function () {
    it('opens for a super admin', function () {
        $this->actingAs(adminWithAccess(AdminRoles::SUPER_ADMIN), AuthGuard::ADMIN->value)
            ->get(ManageRolePermissions::getUrl())
            ->assertSuccessful();
    });

    it('opens for an admin who was granted permissions.manage', function () {
        $admin = adminWithAccess(AdminRoles::ADMIN, AdminPermissions::PERMISSIONS_MANAGE);

        $this->actingAs($admin, AuthGuard::ADMIN->value)
            ->get(ManageRolePermissions::getUrl())
            ->assertSuccessful();
    });

    it('is forbidden for an admin without the permission and for support', function (AdminRoles $role) {
        $this->actingAs(adminWithAccess($role), AuthGuard::ADMIN->value)
            ->get(ManageRolePermissions::getUrl())
            ->assertForbidden();

        expect(ManageRolePermissions::canAccess())->toBeFalse();
    })->with([AdminRoles::ADMIN, AdminRoles::SUPPORT]);

    it('shows in the navigation for a super admin', function () {
        $this->actingAs(adminWithAccess(AdminRoles::SUPER_ADMIN), AuthGuard::ADMIN->value);

        expect(ManageRolePermissions::canAccess())->toBeTrue();
    });
});

describe('matrix', function () {
    it('renders the resource groups and roles of both guards', function () {
        openPermissionsPage()
            ->assertSee('Admin team')
            ->assertSee('Tenant users')
            ->assertSee('Admin users')
            ->assertSee('Contact requests')
            ->assertSee('View list')
            ->assertSee('Super Admin')
            ->assertSee('Support')
            ->assertSee('View Only')
            ->assertSee('Full access, cannot be changed');
    });

    it('offers each guard only its own roles and permissions', function () {
        $page = openPermissionsPage();

        $adminState = $page->get('data.'.FilamentPanel::ADMIN->value);
        $tenantState = $page->get('data.'.FilamentPanel::TENANT->value);
        $adminIds = Permission::query()->where('guard_name', AuthGuard::ADMIN->value)->pluck('id')->all();
        $tenantIds = Permission::query()->where('guard_name', AuthGuard::TENANT->value)->pluck('id')->all();

        expect(array_keys($adminState))->toEqualCanonicalizing([adminRole(AdminRoles::ADMIN)->id, adminRole(AdminRoles::SUPPORT)->id])
            ->and(array_keys(reset($adminState)))->each->toBeIn($adminIds)
            ->and(array_keys(reset($tenantState)))->each->toBeIn($tenantIds);
    });

    it('leaves the locked roles out of the editable state', function () {
        $page = openPermissionsPage();
        $owner = Role::findByName(TenantRoles::OWNER->value, AuthGuard::TENANT->value);

        expect($page->get('data.'.FilamentPanel::ADMIN->value))->not->toHaveKey(adminRole(AdminRoles::SUPER_ADMIN)->id)
            ->and($page->get('data.'.FilamentPanel::TENANT->value))->not->toHaveKey($owner->id);
    });

    it('only offers invitation permissions while the invitations feature is on', function (bool $enabled) {
        config(['tenancy-foundation.features.invitations' => $enabled]);
        $this->seed(TenantRolePermissionsSeeder::class);

        $page = openPermissionsPage();

        $enabled ? $page->assertSee('Invitations') : $page->assertDontSee('Invitations');
    })->with([true, false]);

    it('tells the admin to run sync-permissions for enum permissions without a row, and creates nothing', function () {
        Permission::findByName(AdminPermissions::TENANT_CANCEL->value, AuthGuard::ADMIN->value)->delete();
        $count = Permission::query()->count();

        openPermissionsPage()
            ->assertSee('Some permissions have not been created yet')
            ->assertSee('tenancy-foundation:sync-permissions');

        expect(Permission::query()->count())->toBe($count);
    });

    it('shows database permissions without an enum case as read-only and keeps them on save', function () {
        $legacy = Permission::findOrCreate('legacy.export', AuthGuard::ADMIN->value);
        $support = adminRole(AdminRoles::SUPPORT);
        $support->givePermissionTo($legacy);
        $tenantUpdate = adminPermissionId(AdminPermissions::TENANT_UPDATE);

        openPermissionsPage()
            ->assertSee('Unknown permissions')
            ->assertSee('legacy.export')
            ->set("data.admin.{$support->id}.{$tenantUpdate}", true)
            ->call('save');

        expect($support->fresh()->hasPermissionTo('legacy.export'))->toBeTrue()
            ->and($support->fresh()->hasPermissionTo(AdminPermissions::TENANT_UPDATE->value))->toBeTrue();
    });
});

describe('saving', function () {
    it('grants and revokes permissions straight away and records who did it', function () {
        $superAdmin = adminWithAccess(AdminRoles::SUPER_ADMIN);
        $support = adminRole(AdminRoles::SUPPORT);
        $supportUser = adminWithAccess(AdminRoles::SUPPORT);
        $grant = adminPermissionId(AdminPermissions::TENANT_UPDATE);
        $revoke = adminPermissionId(AdminPermissions::USER_SEND_PASSWORD_RESET);

        expect($supportUser->can(AdminPermissions::TENANT_UPDATE))->toBeFalse()
            ->and($supportUser->can(AdminPermissions::USER_SEND_PASSWORD_RESET))->toBeTrue();

        openPermissionsPage($superAdmin)
            ->set("data.admin.{$support->id}.{$grant}", true)
            ->set("data.admin.{$support->id}.{$revoke}", false)
            ->callAction('save')
            ->assertNotified('Permissions saved for 1 role');

        $supportUser = $supportUser->fresh();
        $change = RolePermissionChange::query()->sole();

        expect($support->fresh()->hasPermissionTo(AdminPermissions::TENANT_UPDATE->value))->toBeTrue()
            ->and($support->fresh()->hasPermissionTo(AdminPermissions::USER_SEND_PASSWORD_RESET->value))->toBeFalse()
            ->and(Gate::forUser($supportUser)->allows(AdminPermissions::TENANT_UPDATE))->toBeTrue()
            ->and(Gate::forUser($supportUser)->allows(AdminPermissions::USER_SEND_PASSWORD_RESET))->toBeFalse()
            ->and($change->role_name)->toBe(AdminRoles::SUPPORT->value)
            ->and($change->added)->toBe([AdminPermissions::TENANT_UPDATE->value])
            ->and($change->removed)->toBe([AdminPermissions::USER_SEND_PASSWORD_RESET->value])
            ->and($change->actor->is($superAdmin))->toBeTrue();
    });

    it('lists each change in plain words and warns it applies to every tenant before saving', function () {
        $support = adminRole(AdminRoles::SUPPORT);
        $grant = adminPermissionId(AdminPermissions::TENANT_UPDATE);

        openPermissionsPage()
            ->set("data.admin.{$support->id}.{$grant}", true)
            ->mountAction('save')
            ->assertMountedActionModalSee(['Applies to every tenant', 'Support (Admin team)', 'Gains: Tenants: Edit']);

        expect(RolePermissionChange::query()->count())->toBe(0);
    });

    it('counts unsaved changes and disables saving and resetting until something changed', function () {
        $support = adminRole(AdminRoles::SUPPORT);
        $grant = adminPermissionId(AdminPermissions::TENANT_UPDATE);

        openPermissionsPage()
            ->assertActionDisabled('save')
            ->assertActionDisabled('reset')
            ->set("data.admin.{$support->id}.{$grant}", true)
            ->assertSee('1 unsaved change')
            ->assertActionEnabled('save')
            ->callAction('reset')
            ->assertDontSee('unsaved change')
            ->assertActionDisabled('save');
    });

    it('says nothing changed and writes nothing when the state matches the database', function () {
        openPermissionsPage()
            ->call('save')
            ->assertNotified('Nothing changed');

        expect(RolePermissionChange::query()->count())->toBe(0);
    });

    it('ignores a permission of the wrong guard', function () {
        $support = adminRole(AdminRoles::SUPPORT);
        $tenantPermission = Permission::findByName(TenantPermissions::TENANT_DELETE->value, AuthGuard::TENANT->value);

        openPermissionsPage()
            ->set("data.admin.{$support->id}.{$tenantPermission->id}", true)
            ->call('save')
            ->assertNotified('Nothing changed');

        expect($support->fresh()->permissions()->pluck('name')->all())->not->toContain(TenantPermissions::TENANT_DELETE->value)
            ->and(RolePermissionChange::query()->count())->toBe(0);
    });

    it('never changes a locked role, even if its state is tampered with', function () {
        $superAdmin = adminRole(AdminRoles::SUPER_ADMIN);
        $permission = adminPermissionId(AdminPermissions::TENANT_UPDATE);

        openPermissionsPage()
            ->set("data.admin.{$superAdmin->id}.{$permission}", true)
            ->call('save')
            ->assertNotified('Nothing changed');

        expect($superAdmin->fresh()->permissions()->count())->toBe(0);
    });

    it('reports a role the action refuses to change as a notification', function () {
        $support = adminRole(AdminRoles::SUPPORT);
        $grant = adminPermissionId(AdminPermissions::TENANT_UPDATE);

        $this->mock(ApplyPermissionMatrix::class)
            ->shouldReceive('handle')
            ->andReturn(['applied' => [], 'failed' => [AdminRoles::SUPPORT->value => 'Refused.']]);

        openPermissionsPage()
            ->set("data.admin.{$support->id}.{$grant}", true)
            ->call('save')
            ->assertNotified('Could not save the Support role');
    });

    it('selects all and none of a resource for one role in a single click', function () {
        $support = adminRole(AdminRoles::SUPPORT);
        $ids = Permission::query()->where('guard_name', AuthGuard::ADMIN->value)->where('name', 'like', 'tenant.%')->pluck('id')->all();

        $page = openPermissionsPage()->call('toggleGroup', FilamentPanel::ADMIN->value, $support->id, $ids);

        foreach ($ids as $id) {
            $page->assertSet("data.admin.{$support->id}.{$id}", true);
        }

        $page->call('toggleGroup', FilamentPanel::ADMIN->value, $support->id, $ids);

        foreach ($ids as $id) {
            $page->assertSet("data.admin.{$support->id}.{$id}", false);
        }
    });
});
