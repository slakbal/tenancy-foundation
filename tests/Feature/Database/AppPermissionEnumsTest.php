<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Database\Seeders\TenantRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\InvitationPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Exceptions\InvalidPermissionSeeder;
use Slakbal\TenancyFoundation\Filament\Admin\Pages\ManageRolePermissions;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Support\Permissions\PermissionLabels;
use Slakbal\TenancyFoundation\Support\Permissions\TenantPermissionEnums;
use Slakbal\TenancyFoundation\Tests\Support\AppPermissions;
use Slakbal\TenancyFoundation\Tests\Support\AppTenantRolePermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

const APP_SYNC_COMMAND = 'tenancy-foundation:sync-permissions';

function useAppSeeder(): void
{
    config([TenantPermissionEnums::CONFIG_KEY => AppTenantRolePermissionsSeeder::class]);
}

function appPermission(AppPermissions $permission): Permission
{
    return Permission::findByName($permission->value, AuthGuard::TENANT->value);
}

describe('resolver', function () {
    it('returns the package enums when the key is the default or absent', function () {
        $expected = [TenantPermissions::class, InvitationPermissions::class];

        expect(app(TenantPermissionEnums::class)->classes())->toBe($expected);

        config()->offsetUnset(TenantPermissionEnums::CONFIG_KEY);

        expect(app(TenantPermissionEnums::class)->classes())->toBe($expected);
    });

    it('still gates the invitation permissions on the feature flag for an app seeder', function () {
        useAppSeeder();
        config(['tenancy-foundation.features.invitations' => false]);

        expect(app(TenantPermissionEnums::class)->classes())->toBe([TenantPermissions::class, AppPermissions::class]);
    });

    it('adds the app enum to the permission names', function () {
        useAppSeeder();

        expect(app(TenantPermissionEnums::class)->names())->toContain(AppPermissions::INVOICE_APPROVE->value, TenantPermissions::USER_VIEW->value);
    });

    it('fails loudly when the configured class is not a tenant role permissions seeder', function (mixed $configured) {
        config([TenantPermissionEnums::CONFIG_KEY => $configured]);

        app(TenantPermissionEnums::class)->classes();
    })->with([
        'unrelated class' => [stdClass::class],
        'missing class' => ['App\\Database\\Seeders\\DoesNotExist'],
        'not a string' => [42],
    ])->throws(InvalidPermissionSeeder::class);
});

describe('labels', function () {
    it('falls back to a headline for an app group and action', function () {
        expect(PermissionLabels::group(AppPermissions::PURCHASE_ORDER_EXPORT->value))->toBe('Purchase Order')
            ->and(PermissionLabels::action(AppPermissions::PURCHASE_ORDER_EXPORT->value))->toBe('Export All')
            ->and(PermissionLabels::full(AppPermissions::INVOICE_VIEW_ANY->value))->toBe('Invoice: View list');
    });
});

describe('sync command', function () {
    beforeEach(function () {
        $this->seed(TenantRolePermissionsSeeder::class);
        useAppSeeder();
    });

    it('creates the missing rows for the app enum cases and leaves assignments alone', function () {
        $this->artisan(APP_SYNC_COMMAND)->assertSuccessful();

        foreach (AppPermissions::cases() as $case) {
            expect(Permission::where('name', $case->value)->where('guard_name', AuthGuard::TENANT->value)->exists())->toBeTrue();
        }

        Role::where('guard_name', AuthGuard::TENANT->value)->get()->each(
            fn (Role $role) => expect($role->permissions->pluck('name')->all())->not->toContain(AppPermissions::INVOICE_APPROVE->value)
        );
    });

    it('reports a database permission in no enum as an orphan but not an app enum case', function () {
        $this->artisan(APP_SYNC_COMMAND)->assertSuccessful();
        Permission::create(['name' => 'legacy.thing', 'guard_name' => AuthGuard::TENANT->value]);

        $this->artisan(APP_SYNC_COMMAND)
            ->expectsOutputToContain('legacy.thing')
            ->assertSuccessful();
    });

    it('would report the app rows as orphans without the configured seeder', function () {
        $this->artisan(APP_SYNC_COMMAND)->assertSuccessful();
        config([TenantPermissionEnums::CONFIG_KEY => TenantRolePermissionsSeeder::class]);

        $this->artisan(APP_SYNC_COMMAND)->expectsOutputToContain(AppPermissions::INVOICE_APPROVE->value)->assertSuccessful();
    });
});

describe('role permissions page', function () {
    beforeEach(function () {
        Filament::setCurrentPanel(Filament::getPanel(FilamentPanel::ADMIN->getPanelId()));
        Filament::bootCurrentPanel();
        setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

        $this->seed(TenantRolePermissionsSeeder::class);
        useAppSeeder();
        $this->artisan(APP_SYNC_COMMAND)->assertSuccessful();

        $this->actingAs(adminWithAccess(AdminRoles::SUPER_ADMIN), AuthGuard::ADMIN->value);
    });

    it('lists the app permissions as editable rows, not as unknown', function () {
        Livewire::test(ManageRolePermissions::class)
            ->assertSee('Invoice')
            ->assertSee('Purchase Order')
            ->assertSee('Export All')
            ->assertDontSee('Unknown permissions')
            ->assertDontSee(AppPermissions::INVOICE_APPROVE->value);
    });

    it('keeps a database permission that no enum defines unknown', function () {
        Permission::findOrCreate('legacy.thing', AuthGuard::TENANT->value);

        Livewire::test(ManageRolePermissions::class)
            ->assertSee('Unknown permissions')
            ->assertSee('legacy.thing');
    });

    it('lists the app permissions as unknown when the app seeder is not configured', function () {
        config([TenantPermissionEnums::CONFIG_KEY => TenantRolePermissionsSeeder::class]);

        Livewire::test(ManageRolePermissions::class)
            ->assertSee('Unknown permissions')
            ->assertSee(AppPermissions::INVOICE_APPROVE->value);
    });

    it('grants an app permission to a role through the page and the member can then act', function () {
        Filament::setCurrentPanel(Filament::getPanel(FilamentPanel::TENANT->getPanelId()));
        Filament::bootCurrentPanel();

        $tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], User::factory()->create());
        setPermissionsTeamId($tenant->getKey());
        $member = User::factory()->create();
        $tenant->members()->attach($member->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);
        app(AssignTenantRole::class)->handle($member, $tenant, TenantRoles::VIEW_ONLY);

        $role = Role::findByName(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);
        $permissionId = appPermission(AppPermissions::INVOICE_APPROVE)->getKey();

        expect($member->fresh()->can(AppPermissions::INVOICE_APPROVE->value))->toBeFalse();

        Filament::setCurrentPanel(Filament::getPanel(FilamentPanel::ADMIN->getPanelId()));
        Filament::bootCurrentPanel();
        setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);
        $this->actingAs(adminWithAccess(AdminRoles::SUPER_ADMIN), AuthGuard::ADMIN->value);

        Livewire::test(ManageRolePermissions::class)
            ->set('data.'.FilamentPanel::TENANT->value.".{$role->id}.{$permissionId}", true)
            ->callAction('save')
            ->assertNotified('Permissions saved for 1 role');

        setPermissionsTeamId($tenant->getKey());

        expect($role->fresh()->hasPermissionTo(AppPermissions::INVOICE_APPROVE->value))->toBeTrue()
            ->and($member->fresh()->can(AppPermissions::INVOICE_APPROVE->value))->toBeTrue();
    });
});
