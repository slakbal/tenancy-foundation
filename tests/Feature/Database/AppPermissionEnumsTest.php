<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Database\Seeders\TenantRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\InvitationPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Exceptions\InvalidPermissionSeeder;
use Slakbal\TenancyFoundation\Filament\Admin\Pages\ManageRolePermissions;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Support\Permissions\AdminPermissionEnums;
use Slakbal\TenancyFoundation\Support\Permissions\PermissionLabels;
use Slakbal\TenancyFoundation\Support\Permissions\TenantPermissionEnums;
use Slakbal\TenancyFoundation\Tests\Support\AppAdminPermissions;
use Slakbal\TenancyFoundation\Tests\Support\AppAdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Tests\Support\AppPermissions;
use Slakbal\TenancyFoundation\Tests\Support\AppTenantRolePermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

const APP_SYNC_COMMAND = 'tenancy-foundation:sync-permissions';

function useAppSeeders(): void
{
    config([
        AdminPermissionEnums::CONFIG_KEY => AppAdminRolePermissionsSeeder::class,
        TenantPermissionEnums::CONFIG_KEY => AppTenantRolePermissionsSeeder::class,
    ]);
}

function appPermission(AppPermissions $permission): Permission
{
    return Permission::findByName($permission->value, AuthGuard::TENANT->value);
}

dataset('app hooks', [
    'admin' => [FilamentPanel::ADMIN, AdminPermissionEnums::CONFIG_KEY, AdminRolePermissionsSeeder::class, AppAdminPermissions::AUDIT_LOG_EXPORT_CSV],
    'tenant' => [FilamentPanel::TENANT, TenantPermissionEnums::CONFIG_KEY, TenantRolePermissionsSeeder::class, AppPermissions::INVOICE_APPROVE],
]);

describe('resolver', function () {
    it('returns the package enums when the key is the default or absent', function (string $resolver, array $expected) {
        expect(app($resolver)->classes())->toBe($expected);

        config()->offsetUnset($resolver::CONFIG_KEY);

        expect(app($resolver)->classes())->toBe($expected);
    })->with([
        'admin' => [AdminPermissionEnums::class, [AdminPermissions::class]],
        'tenant' => [TenantPermissionEnums::class, [TenantPermissions::class, InvitationPermissions::class]],
    ]);

    it('still gates the invitation permissions on the feature flag for an app seeder', function () {
        useAppSeeders();
        config(['tenancy-foundation.features.invitations' => false]);

        expect(app(TenantPermissionEnums::class)->classes())->toBe([TenantPermissions::class, AppPermissions::class]);
    });

    it('adds the app enums to the permission names', function () {
        useAppSeeders();

        expect(app(AdminPermissionEnums::class)->names())->toContain(AppAdminPermissions::AUDIT_LOG_EXPORT_CSV->value, AdminPermissions::USER_VIEW->value)
            ->and(app(TenantPermissionEnums::class)->names())->toContain(AppPermissions::INVOICE_APPROVE->value, TenantPermissions::USER_VIEW->value);
    });

    it('fails loudly when the configured class is not a role permissions seeder of its guard', function (string $resolver, mixed $configured) {
        config([$resolver::CONFIG_KEY => $configured]);

        app($resolver)->classes();
    })->with([AdminPermissionEnums::class, TenantPermissionEnums::class])->with([
        'unrelated class' => [stdClass::class],
        'missing class' => ['App\\Database\\Seeders\\DoesNotExist'],
        'not a string' => [42],
    ])->throws(InvalidPermissionSeeder::class);
});

describe('labels', function () {
    it('falls back to a translated headline for an app group and action', function () {
        expect(PermissionLabels::group(AppPermissions::PURCHASE_ORDER_EXPORT->value))->toBe('Purchase Order')
            ->and(PermissionLabels::action(AppPermissions::PURCHASE_ORDER_EXPORT->value))->toBe('Export All')
            ->and(PermissionLabels::full(AppPermissions::INVOICE_VIEW_ANY->value))->toBe('Invoice: View list');

        app('translator')->addLines(['*.Audit Log' => 'Prüfprotokoll', '*.Export Csv' => 'Als CSV exportieren'], 'de');
        app()->setLocale('de');

        expect(PermissionLabels::full(AppAdminPermissions::AUDIT_LOG_EXPORT_CSV->value))->toBe('Prüfprotokoll: Als CSV exportieren');
    });
});

describe('admin seeder', function () {
    it('grants the app admin permissions to the admin role on a first seed, but not permissions.manage or support', function () {
        useAppSeeders();
        $this->seed(AppAdminRolePermissionsSeeder::class);

        $held = fn (AdminRoles $role): array => Role::findByName($role->value, AuthGuard::ADMIN->value)->permissions->pluck('name')->all();

        expect($held(AdminRoles::ADMIN))->toContain(AppAdminPermissions::AUDIT_LOG_VIEW_ANY->value, AppAdminPermissions::AUDIT_LOG_EXPORT_CSV->value)
            ->not->toContain(AdminPermissions::PERMISSIONS_MANAGE->value)
            ->and($held(AdminRoles::SUPPORT))->not->toContain(AppAdminPermissions::AUDIT_LOG_VIEW_ANY->value);
    });
});

describe('sync command', function () {
    beforeEach(function () {
        $this->seed([AdminRolePermissionsSeeder::class, TenantRolePermissionsSeeder::class]);
        useAppSeeders();
    });

    it('creates the missing rows for the app enum cases and leaves assignments alone', function (FilamentPanel $panel, string $configKey, string $packageSeeder, BackedEnum $appPermission) {
        $this->artisan(APP_SYNC_COMMAND, ['--no-interaction' => true])->assertSuccessful();

        foreach ($appPermission::cases() as $case) {
            expect(Permission::where('name', $case->value)->where('guard_name', $panel->getAuthGuard())->exists())->toBeTrue();
        }

        Role::where('guard_name', $panel->getAuthGuard())->get()->each(
            fn (Role $role) => expect($role->permissions->pluck('name')->all())->not->toContain($appPermission->value)
        );
    })->with('app hooks');

    it('reports a database permission in no enum as an orphan but not an app enum case', function () {
        $this->artisan(APP_SYNC_COMMAND, ['--no-interaction' => true])->assertSuccessful();
        Permission::create(['name' => 'legacy.thing', 'guard_name' => AuthGuard::TENANT->value]);

        $this->artisan(APP_SYNC_COMMAND, ['--no-interaction' => true])
            ->expectsOutputToContain('legacy.thing')
            ->assertSuccessful();
    });

    it('would report the app rows as orphans without the configured seeder', function (FilamentPanel $panel, string $configKey, string $packageSeeder, BackedEnum $appPermission) {
        $this->artisan(APP_SYNC_COMMAND, ['--no-interaction' => true])->assertSuccessful();
        config([$configKey => $packageSeeder]);

        $this->artisan(APP_SYNC_COMMAND, ['--no-interaction' => true])->expectsOutputToContain($appPermission->value)->assertSuccessful();
    })->with('app hooks');
});

describe('role permissions page', function () {
    beforeEach(function () {
        Filament::setCurrentPanel(Filament::getPanel(FilamentPanel::ADMIN->getPanelId()));
        Filament::bootCurrentPanel();
        setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

        $this->seed([AdminRolePermissionsSeeder::class, TenantRolePermissionsSeeder::class]);
        useAppSeeders();
        $this->artisan(APP_SYNC_COMMAND, ['--no-interaction' => true])->assertSuccessful();

        $this->actingAs(adminWithAccess(AdminRoles::SUPER_ADMIN), AuthGuard::ADMIN->value);
    });

    it('lists the app permissions of both guards as editable rows, not as unknown', function () {
        Livewire::test(ManageRolePermissions::class)
            ->set('activeTab', FilamentPanel::ADMIN->value)
            ->assertSee('Audit Log')
            ->assertSee('Export Csv')
            ->set('activeTab', FilamentPanel::TENANT->value)
            ->assertSee('Invoice')
            ->assertSee('Purchase Order')
            ->assertSee('Export All')
            ->assertDontSee('Unknown permissions')
            ->assertDontSee(AppAdminPermissions::AUDIT_LOG_EXPORT_CSV->value)
            ->assertDontSee(AppPermissions::INVOICE_APPROVE->value);
    });

    it('keeps a database permission that no enum defines unknown', function () {
        Permission::findOrCreate('legacy.thing', AuthGuard::TENANT->value);

        Livewire::test(ManageRolePermissions::class)
            ->set('activeTab', FilamentPanel::TENANT->value)
            ->assertSee('Unknown permissions')
            ->assertSee('legacy.thing');
    });

    it('lists the app permissions as unknown when the app seeder is not configured', function (FilamentPanel $panel, string $configKey, string $packageSeeder, BackedEnum $appPermission) {
        config([$configKey => $packageSeeder]);

        Livewire::test(ManageRolePermissions::class)
            ->set('activeTab', $panel->value)
            ->assertSee('Unknown permissions')
            ->assertSee($appPermission->value);
    })->with('app hooks');

    it('grants an app admin permission to a role through the page and the admin can then act', function () {
        $support = adminWithAccess(AdminRoles::SUPPORT);
        $role = Role::findByName(AdminRoles::SUPPORT->value, AuthGuard::ADMIN->value);
        $permissionId = Permission::findByName(AppAdminPermissions::AUDIT_LOG_EXPORT_CSV->value, AuthGuard::ADMIN->value)->getKey();

        expect($support->can(AppAdminPermissions::AUDIT_LOG_EXPORT_CSV->value))->toBeFalse();

        Livewire::test(ManageRolePermissions::class)
            ->set('activeTab', FilamentPanel::ADMIN->value)
            ->set('data.'.FilamentPanel::ADMIN->value.".{$role->id}.{$permissionId}", true)
            ->callAction('save')
            ->assertNotified('Permissions saved for 1 role');

        expect($role->fresh()->hasPermissionTo(AppAdminPermissions::AUDIT_LOG_EXPORT_CSV->value))->toBeTrue()
            ->and($support->fresh()->can(AppAdminPermissions::AUDIT_LOG_EXPORT_CSV->value))->toBeTrue();
    });

    it('grants an app permission to a role through the page and the member can then act', function () {
        Filament::setCurrentPanel(Filament::getPanel(FilamentPanel::TENANT->getPanelId()));
        Filament::bootCurrentPanel();

        $tenant = app(CreateTenant::class)->handle(['name' => 'Acme', ...tenantAddress('acme')], User::factory()->create());
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
            ->set('activeTab', FilamentPanel::TENANT->value)
            ->set('data.'.FilamentPanel::TENANT->value.".{$role->id}.{$permissionId}", true)
            ->callAction('save')
            ->assertNotified('Permissions saved for 1 role');

        setPermissionsTeamId($tenant->getKey());

        expect($role->fresh()->hasPermissionTo(AppPermissions::INVOICE_APPROVE->value))->toBeTrue()
            ->and($member->fresh()->can(AppPermissions::INVOICE_APPROVE->value))->toBeTrue();
    });
});
