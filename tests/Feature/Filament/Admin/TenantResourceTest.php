<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Tenant\CancelTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\TenantStatus;
use Slakbal\TenancyFoundation\Events\TenantReactivated;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages\ListTenants;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\TenantResource;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Notifications\AccountCancelled;
use Slakbal\TenancyFoundation\Plugins\AdminPlugin;

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
    $admin = adminWithAccess(AdminPermissions::TENANT_VIEW_ANY, AdminPermissions::TENANT_VIEW, AdminPermissions::TENANT_CANCEL, AdminPermissions::TENANT_REACTIVATE);
    test()->actingAs($admin, AuthGuard::ADMIN->value);

    return $admin;
}

test('an admin without the view permission cannot open the tenants list', function () {
    $this->actingAs(Admin::factory()->create(), AuthGuard::ADMIN->value);

    Livewire::test(ListTenants::class)->assertForbidden();
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

test('cancelling is only offered for active tenants and reactivating only for cancelled ones', function (string $actor) {
    if ($actor === 'super admin') {
        $this->actingAs(adminWithAccess(AdminRoles::SUPER_ADMIN), AuthGuard::ADMIN->value);
    } else {
        actAsManager();
    }

    Livewire::test(ListTenants::class)
        ->assertSuccessful()
        ->assertTableActionVisible('cancel', $this->live)
        ->assertTableActionHidden('cancel', $this->gone)
        ->assertTableActionVisible('reactivate', $this->gone)
        ->assertTableActionHidden('reactivate', $this->live);
})->with(['a manager', 'super admin']);

test('an admin can cancel a tenant for maintenance and the owners are emailed', function () {
    actAsManager();
    Notification::fake();

    Livewire::test(ListTenants::class)
        ->callTableAction('cancel', $this->live)
        ->assertNotified();

    $live = $this->live->fresh();

    expect($live->isCancelled())->toBeTrue()
        ->and($live->scheduled_for_deletion_at->toDateTimeString())->toBe('2026-11-27 23:59:59')
        ->and(Tenant::query()->active()->count())->toBe(0)
        ->and(Tenant::query()->count())->toBe(2);
    Notification::assertSentTo($this->live->owners()->get(), AccountCancelled::class);
});

test('an admin can reactivate a cancelled tenant', function () {
    actAsManager();
    Event::fake([TenantReactivated::class]);

    Livewire::test(ListTenants::class)
        ->callTableAction('reactivate', $this->gone)
        ->assertNotified();

    expect($this->gone->fresh()->isCancelled())->toBeFalse()
        ->and($this->gone->fresh()->scheduled_for_deletion_at)->toBeNull()
        ->and(Tenant::query()->active()->count())->toBe(2);
    Event::assertDispatched(TenantReactivated::class);
});

test('the actions need their own permissions', function (array $permissions, bool $canCancel, bool $canReactivate) {
    $this->actingAs(adminWithAccess(...$permissions), AuthGuard::ADMIN->value);

    $list = Livewire::test(ListTenants::class);

    $canCancel
        ? $list->assertTableActionVisible('cancel', $this->live)
        : $list->assertTableActionHidden('cancel', $this->live);
    $canReactivate
        ? $list->assertTableActionVisible('reactivate', $this->gone)
        : $list->assertTableActionHidden('reactivate', $this->gone);
})->with([
    'view only' => [[AdminPermissions::TENANT_VIEW_ANY], false, false],
    'cancel only' => [[AdminPermissions::TENANT_VIEW_ANY, AdminPermissions::TENANT_CANCEL], true, false],
    'reactivate only' => [[AdminPermissions::TENANT_VIEW_ANY, AdminPermissions::TENANT_REACTIVATE], false, true],
]);

test('the tenants resource is registered by the plugin and can be swapped', function () {
    expect(Filament::getPanel('admin')->getResources())->toContain(TenantResource::class);

    $custom = new class extends TenantResource {};
    $panel = Panel::make()->id('swap');

    AdminPlugin::make()->usingTenantResource($custom::class)->register($panel);

    expect($panel->getResources())->toContain($custom::class)
        ->and($panel->getResources())->not->toContain(TenantResource::class);
});
