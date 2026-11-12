<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
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
use Slakbal\TenancyFoundation\Support\Permissions\PermissionMatrix;
use Slakbal\TenancyFoundation\Support\Permissions\PermissionMatrixLoader;
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

/**
 * Binds a loader that records which panels were loaded, in order.
 */
function recordPanelLoads(): object
{
    $loader = new class extends PermissionMatrixLoader
    {
        /** @var array<int, string> */
        public array $loaded = [];

        public function load(FilamentPanel $panel): PermissionMatrix
        {
            $this->loaded[] = $panel->value;

            return parent::load($panel);
        }
    };

    app()->instance(PermissionMatrixLoader::class, $loader);

    return $loader;
}

function openTenantTab($page)
{
    return $page->set('activeTab', FilamentPanel::TENANT->value);
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
    it('renders both tabs, and the resource groups and roles of each guard once opened', function () {
        $page = openPermissionsPage()
            ->assertSee('Admin team')
            ->assertSee('Tenant users')
            ->assertSee('Admin users')
            ->assertSee('Contact requests')
            ->assertSee('View list')
            ->assertSee('Super Admin')
            ->assertSee('Support')
            ->assertSee('Full access, cannot be changed')
            ->assertDontSee('View Only');

        openTenantTab($page)->assertSee('View Only');
    });

    it('offers each guard only its own roles and permissions', function () {
        $page = openTenantTab(openPermissionsPage());

        $adminState = $page->get('data.'.FilamentPanel::ADMIN->value);
        $tenantState = $page->get('data.'.FilamentPanel::TENANT->value);
        $adminIds = Permission::query()->where('guard_name', AuthGuard::ADMIN->value)->pluck('id')->all();
        $tenantIds = Permission::query()->where('guard_name', AuthGuard::TENANT->value)->pluck('id')->all();

        expect(array_keys($adminState))->toEqualCanonicalizing([adminRole(AdminRoles::ADMIN)->id, adminRole(AdminRoles::SUPPORT)->id])
            ->and(array_keys(reset($adminState)))->each->toBeIn($adminIds)
            ->and(array_keys(reset($tenantState)))->each->toBeIn($tenantIds);
    });

    it('leaves the locked roles out of the editable state', function () {
        $page = openTenantTab(openPermissionsPage());
        $owner = Role::findByName(TenantRoles::OWNER->value, AuthGuard::TENANT->value);

        expect($page->get('data.'.FilamentPanel::ADMIN->value))->not->toHaveKey(adminRole(AdminRoles::SUPER_ADMIN)->id)
            ->and($page->get('data.'.FilamentPanel::TENANT->value))->not->toHaveKey($owner->id);
    });

    it('only offers invitation permissions while the invitations feature is on', function (bool $enabled) {
        config(['tenancy-foundation.features.invitations' => $enabled]);
        $this->seed(TenantRolePermissionsSeeder::class);

        $page = openTenantTab(openPermissionsPage());

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

describe('lazy tabs', function () {
    it('loads only the active guard on first render and the other guard when its tab opens', function () {
        $loader = recordPanelLoads();

        $page = openPermissionsPage();

        expect($loader->loaded)->toBe([FilamentPanel::ADMIN->value])
            ->and($page->get('data'))->toHaveKey(FilamentPanel::ADMIN->value)
            ->and($page->get('data'))->not->toHaveKey(FilamentPanel::TENANT->value);

        $loader->loaded = [];
        openTenantTab($page);

        expect($page->get('data'))->toHaveKeys([FilamentPanel::ADMIN->value, FilamentPanel::TENANT->value]);
        expect($loader->loaded)->toContain(FilamentPanel::TENANT->value);

        expect($page->get('original'))->toHaveCount(2);
    });

    it('keeps unsaved edits of a tab while switching away and back', function () {
        $support = adminRole(AdminRoles::SUPPORT);
        $grant = adminPermissionId(AdminPermissions::TENANT_UPDATE);

        $page = openPermissionsPage()->set("data.admin.{$support->id}.{$grant}", true);

        openTenantTab($page)
            ->set('activeTab', FilamentPanel::ADMIN->value)
            ->assertSet("data.admin.{$support->id}.{$grant}", true)
            ->assertSee('1 unsaved change');
    });

    it('saves the edits of both tabs and nothing else', function () {
        $support = adminRole(AdminRoles::SUPPORT);
        $grant = adminPermissionId(AdminPermissions::TENANT_UPDATE);
        $viewOnly = Role::findByName(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);
        $tenantGrant = Permission::findByName(TenantPermissions::TENANT_DELETE->value, AuthGuard::TENANT->value)->getKey();

        $page = openPermissionsPage()->set("data.admin.{$support->id}.{$grant}", true);
        openTenantTab($page)
            ->set("data.app.{$viewOnly->id}.{$tenantGrant}", true)
            ->assertSee('2 unsaved changes')
            ->call('save')
            ->assertNotified('Permissions saved for 2 roles')
            ->assertSee('Reset');

        expect($support->fresh()->hasPermissionTo(AdminPermissions::TENANT_UPDATE->value))->toBeTrue()
            ->and($viewOnly->fresh()->hasPermissionTo(TenantPermissions::TENANT_DELETE->value))->toBeTrue()
            ->and(RolePermissionChange::query()->count())->toBe(2);
    });

    it('saves only the loaded tab when the other was never opened', function () {
        $this->mock(ApplyPermissionMatrix::class)
            ->shouldReceive('handle')
            ->once()
            ->andReturn(['applied' => [], 'failed' => []]);

        openPermissionsPage()->call('save');
    });

    it('ignores state sent for a tab that is not loaded', function () {
        $viewOnly = Role::findByName(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);
        $tenantGrant = Permission::findByName(TenantPermissions::TENANT_DELETE->value, AuthGuard::TENANT->value)->getKey();

        $page = openPermissionsPage()
            ->set('data.app', [$viewOnly->id => [$tenantGrant => true]])
            ->assertDontSee('unsaved change')
            ->assertActionDisabled('save')
            ->call('save');

        expect($viewOnly->fresh()->hasPermissionTo(TenantPermissions::TENANT_DELETE->value))->toBeFalse()
            ->and(RolePermissionChange::query()->count())->toBe(0);

        $page->assertSet('data', fn (array $data): bool => array_keys($data) === [FilamentPanel::ADMIN->value]);
    });

    it('does not let the client mark a panel as loaded', function () {
        openPermissionsPage()->set('original', [FilamentPanel::TENANT->value => []]);
    })->throws(CannotUpdateLockedPropertyException::class);

    it('falls back to the first tab for an unknown tab key', function () {
        openPermissionsPage()
            ->set('activeTab', 'nonsense')
            ->assertSet('activeTab', FilamentPanel::ADMIN->value);
    });
});
