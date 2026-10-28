<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\CancelTenant;
use Slakbal\TenancyFoundation\Actions\CreateTenant;
use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\TenantStatus;
use Slakbal\TenancyFoundation\Events\TenantCancelled;
use Slakbal\TenancyFoundation\Events\TenantReactivated;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages\ListTenants;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\TenantResource;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Notifications\AccountCancelled;
use Slakbal\TenancyFoundation\Plugins\AdminPlugin;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function adminWith(AdminPermissions ...$permissions): Admin
{
    $admin = Admin::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission->value, AuthGuard::ADMIN->value);
        $admin->givePermissionTo($permission);
    }

    return $admin;
}

function tenantWithOwner(string $subdomain): Tenant
{
    return app(CreateTenant::class)->handle(['name' => ucfirst($subdomain), 'subdomain' => $subdomain], User::factory()->create(['email' => "{$subdomain}@owner.test"]));
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(config('tenancy-foundation.admin_permissions_team_id'));

    $this->travelTo('2026-10-28 09:30:00');
    config(['tenancy-foundation.cancellation.grace_days' => 30]);

    $this->live = tenantWithOwner('live');
    $this->gone = app(CancelTenant::class)->handle(tenantWithOwner('gone'));

    setPermissionsTeamId(config('tenancy-foundation.admin_permissions_team_id'));
});

function actAsManager(): Admin
{
    $admin = adminWith(AdminPermissions::TENANT_VIEW_ANY, AdminPermissions::TENANT_VIEW, AdminPermissions::TENANT_CANCEL, AdminPermissions::TENANT_REACTIVATE);
    test()->actingAs($admin, AuthGuard::ADMIN->value);

    return $admin;
}

test('an admin without the view permission cannot open the tenants list', function () {
    $this->actingAs(Admin::factory()->create(), AuthGuard::ADMIN->value);

    Livewire::test(ListTenants::class)->assertForbidden();
});

test('the list shows every tenant active or cancelled', function () {
    actAsManager();

    Livewire::test(ListTenants::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$this->live, $this->gone]);
});

test('the list shows the status and the deletion date, without owner or member columns', function () {
    actAsManager();

    Livewire::test(ListTenants::class)
        ->assertTableColumnStateSet('status', TenantStatus::ACTIVE, $this->live)
        ->assertTableColumnStateSet('status', TenantStatus::CANCELLED, $this->gone)
        ->assertTableColumnDoesNotExist('owners.email')
        ->assertTableColumnDoesNotExist('members_count')
        ->assertTableColumnVisible('created_at')
        ->assertTableColumnStateSet('scheduled_for_deletion_at', $this->gone->scheduled_for_deletion_at, $this->gone)
        ->assertTableColumnStateSet('scheduled_for_deletion_at', null, $this->live);
});

test('tenants can be searched by name and subdomain', function () {
    actAsManager();

    Livewire::test(ListTenants::class)
        ->searchTable('gone')
        ->assertCanSeeTableRecords([$this->gone])
        ->assertCanNotSeeTableRecords([$this->live])
        ->searchTable('Live')
        ->assertCanSeeTableRecords([$this->live])
        ->assertCanNotSeeTableRecords([$this->gone]);
});

test('the status filter separates active from cancelled tenants', function () {
    actAsManager();

    Livewire::test(ListTenants::class)
        ->filterTable('status', 'active')
        ->assertCanSeeTableRecords([$this->live])
        ->assertCanNotSeeTableRecords([$this->gone])
        ->filterTable('status', 'cancelled')
        ->assertCanSeeTableRecords([$this->gone])
        ->assertCanNotSeeTableRecords([$this->live]);
});

test('the due for deletion filter only shows tenants whose moment has passed', function () {
    actAsManager();
    $due = tenantWithOwner('due');
    $due->forceFill(['cancelled_at' => now()->subDays(40), 'scheduled_for_deletion_at' => now()->subDay()])->save();

    Livewire::test(ListTenants::class)
        ->filterTable('due_for_deletion', true)
        ->assertCanSeeTableRecords([$due])
        ->assertCanNotSeeTableRecords([$this->live, $this->gone]);
});

test('the list runs the same number of queries however many tenants there are', function () {
    actAsManager();
    $count = function (): int {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        Livewire::test(ListTenants::class)->loadTable()->assertSuccessful();

        return $queries;
    };

    $count();
    $few = $count();
    foreach (range(1, 8) as $i) {
        tenantWithOwner("extra{$i}");
    }
    setPermissionsTeamId(config('tenancy-foundation.admin_permissions_team_id'));

    expect($count())->toBe($few);
});

test('cancelling is only offered for active tenants and reactivating only for cancelled ones', function () {
    actAsManager();

    Livewire::test(ListTenants::class)
        ->assertTableActionVisible('cancel', $this->live)
        ->assertTableActionHidden('cancel', $this->gone)
        ->assertTableActionVisible('reactivate', $this->gone)
        ->assertTableActionHidden('reactivate', $this->live);
});

test('an admin can cancel a tenant for maintenance', function () {
    actAsManager();
    Event::fake([TenantCancelled::class]);

    Livewire::test(ListTenants::class)
        ->callTableAction('cancel', $this->live)
        ->assertNotified();

    $live = $this->live->fresh();

    expect($live->isCancelled())->toBeTrue()
        ->and($live->scheduled_for_deletion_at->toDateTimeString())->toBe('2026-11-27 23:59:59');
    Event::assertDispatched(TenantCancelled::class);
});

test('cancelling from the admin panel emails the owners', function () {
    actAsManager();
    Notification::fake();

    Livewire::test(ListTenants::class)->callTableAction('cancel', $this->live);

    Notification::assertSentTo($this->live->owners()->get(), AccountCancelled::class);
});

test('an admin can reactivate a cancelled tenant', function () {
    actAsManager();
    Event::fake([TenantReactivated::class]);

    Livewire::test(ListTenants::class)
        ->callTableAction('reactivate', $this->gone)
        ->assertNotified();

    expect($this->gone->fresh()->isCancelled())->toBeFalse()
        ->and($this->gone->fresh()->scheduled_for_deletion_at)->toBeNull();
    Event::assertDispatched(TenantReactivated::class);
});

test('only that tenant changes when an admin cancels or reactivates', function () {
    actAsManager();

    Livewire::test(ListTenants::class)->callTableAction('cancel', $this->live);

    expect(Tenant::query()->active()->count())->toBe(0)
        ->and(Tenant::query()->count())->toBe(2);

    Livewire::test(ListTenants::class)->callTableAction('reactivate', $this->gone);

    expect(Tenant::query()->active()->pluck('subdomain')->all())->toBe(['gone']);
});

test('the actions need their own permissions', function () {
    $this->actingAs(adminWith(AdminPermissions::TENANT_VIEW_ANY), AuthGuard::ADMIN->value);

    Livewire::test(ListTenants::class)
        ->assertTableActionHidden('cancel', $this->live)
        ->assertTableActionHidden('reactivate', $this->gone);
});

test('an admin who may only cancel cannot reactivate and the other way round', function () {
    $this->actingAs(adminWith(AdminPermissions::TENANT_VIEW_ANY, AdminPermissions::TENANT_CANCEL), AuthGuard::ADMIN->value);

    Livewire::test(ListTenants::class)
        ->assertTableActionVisible('cancel', $this->live)
        ->assertTableActionHidden('reactivate', $this->gone);

    $this->actingAs(adminWith(AdminPermissions::TENANT_VIEW_ANY, AdminPermissions::TENANT_REACTIVATE), AuthGuard::ADMIN->value);

    Livewire::test(ListTenants::class)
        ->assertTableActionHidden('cancel', $this->live)
        ->assertTableActionVisible('reactivate', $this->gone);
});

test('a super admin can do everything', function () {
    Role::findOrCreate(AdminRoles::SUPER_ADMIN->value, AuthGuard::ADMIN->value);
    $admin = Admin::factory()->create();
    $admin->assignRole(AdminRoles::SUPER_ADMIN);
    $this->actingAs($admin, AuthGuard::ADMIN->value);

    Livewire::test(ListTenants::class)
        ->assertSuccessful()
        ->assertTableActionVisible('cancel', $this->live)
        ->assertTableActionVisible('reactivate', $this->gone);
});

test('the admin account is left untouched by cancelling and reactivating', function () {
    $admin = actAsManager();
    $permissions = $admin->getAllPermissions()->count();

    Livewire::test(ListTenants::class)
        ->callTableAction('cancel', $this->live)
        ->callTableAction('reactivate', $this->gone);

    expect($admin->fresh()->getAllPermissions()->count())->toBe($permissions)
        ->and(Admin::query()->whereKey($admin->getKey())->exists())->toBeTrue();
});

test('the admin and support roles get the right tenant permissions from the seeder', function () {
    $this->seed(AdminRolePermissionsSeeder::class);
    setPermissionsTeamId(config('tenancy-foundation.admin_permissions_team_id'));

    $names = fn (AdminRoles $role) => Role::findByName($role->value, AuthGuard::ADMIN->value)->permissions->pluck('name')->filter(fn ($name) => str_starts_with($name, 'tenant.'))->sort()->values()->all();

    expect($names(AdminRoles::ADMIN))->toBe(['tenant.cancel', 'tenant.reactivate', 'tenant.update', 'tenant.view', 'tenant.view_any'])
        ->and($names(AdminRoles::SUPPORT))->toBe(['tenant.view', 'tenant.view_any']);
});

test('the plugin registers the tenants resource on the admin panel', function () {
    expect(Filament::getPanel('admin')->getResources())->toContain(TenantResource::class);
});

test('the tenants resource can be swapped through the plugin', function () {
    $custom = new class extends TenantResource {};
    $panel = Panel::make()->id('swap');

    AdminPlugin::make()->usingTenantResource($custom::class)->register($panel);

    expect($panel->getResources())->toContain($custom::class)
        ->and($panel->getResources())->not->toContain(TenantResource::class);
});
