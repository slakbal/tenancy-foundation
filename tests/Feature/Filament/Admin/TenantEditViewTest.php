<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Actions\Tenant\CancelTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Enums\MemberStatus;
use Slakbal\TenancyFoundation\Enums\TenantResolution;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages\EditTenant;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages\ListTenants;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages\ViewTenant;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\RelationManagers\UsersRelationManager;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\TenantResource;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\UserResource;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\ImpersonateUserAction;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\Invitation;
use Slakbal\TenancyFoundation\Models\Role;
use Slakbal\TenancyFoundation\Models\User;

function tenantPageAdmin(AdminPermissions ...$permissions): Admin
{
    $admin = adminWithAccess(AdminPermissions::TENANT_VIEW_ANY, ...$permissions);
    test()->actingAs($admin, AuthGuard::ADMIN->value);

    return $admin;
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

    $this->owner = User::factory()->create(['email' => 'owner@acme.test']);
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', ...tenantAddress('acme')], $this->owner);
    $this->other = app(CreateTenant::class)->handle(['name' => 'Globex', ...tenantAddress('globex')], User::factory()->create());

    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);
});

test('viewing a tenant needs the view permission', function () {
    tenantPageAdmin();

    Livewire::test(ViewTenant::class, ['record' => $this->tenant->getRouteKey()])->assertForbidden();

    tenantPageAdmin(AdminPermissions::TENANT_VIEW);

    Livewire::test(ViewTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Acme')
        ->assertSee('acme');
});

test('editing a tenant needs the update permission', function () {
    tenantPageAdmin(AdminPermissions::TENANT_VIEW);

    Livewire::test(EditTenant::class, ['record' => $this->tenant->getRouteKey()])->assertForbidden();

    tenantPageAdmin(AdminPermissions::TENANT_VIEW, AdminPermissions::TENANT_UPDATE);

    Livewire::test(EditTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->assertSuccessful()
        ->assertSchemaStateSet(['name' => 'Acme', ...tenantAddress('acme')]);
});

test('an admin can update the name, address and billing email', function () {
    tenantPageAdmin(AdminPermissions::TENANT_VIEW, AdminPermissions::TENANT_UPDATE);

    Livewire::test(EditTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->fillForm(['name' => 'Acme Corp', ...tenantAddress('acme-corp'), 'billing_email' => 'billing@acme.test'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->tenant->refresh()->only(['name', TenantResolution::current()->attribute(), 'billing_email']))
        ->toBe(['name' => 'Acme Corp', ...tenantAddress('acme-corp'), 'billing_email' => 'billing@acme.test']);
});

test('the edit form validates the name, a unique address and the email', function () {
    tenantPageAdmin(AdminPermissions::TENANT_VIEW, AdminPermissions::TENANT_UPDATE);

    Livewire::test(EditTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->fillForm(['name' => '', ...tenantAddress('globex'), 'billing_email' => 'nope'])
        ->call('save')
        ->assertHasFormErrors(['name' => 'required', TenantResolution::current()->attribute() => 'unique', 'billing_email' => 'email']);

    Livewire::test(EditTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->fillForm(tenantAddress('acme'))
        ->call('save')
        ->assertHasNoFormErrors();
});

test('a tenant can be cancelled and reactivated from the view page, but only with the permission', function () {
    tenantPageAdmin(AdminPermissions::TENANT_VIEW);

    Livewire::test(ViewTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->assertActionHidden('cancel');

    tenantPageAdmin(AdminPermissions::TENANT_VIEW, AdminPermissions::TENANT_CANCEL, AdminPermissions::TENANT_REACTIVATE);

    Livewire::test(ViewTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->assertActionVisible('cancel')
        ->assertActionHidden('reactivate')
        ->callAction('cancel');

    expect($this->tenant->refresh()->isCancelled())->toBeTrue();
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

    Livewire::test(ViewTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->assertActionHidden('cancel')
        ->callAction('reactivate');

    expect($this->tenant->refresh()->isCancelled())->toBeFalse();
});

test('the list rows link to the view and edit pages', function () {
    tenantPageAdmin(AdminPermissions::TENANT_VIEW_ANY, AdminPermissions::TENANT_VIEW, AdminPermissions::TENANT_UPDATE);

    Livewire::test(ListTenants::class)
        ->assertTableActionVisible('view', $this->tenant)
        ->assertTableActionVisible('edit', $this->tenant);

    tenantPageAdmin(AdminPermissions::TENANT_VIEW_ANY, AdminPermissions::TENANT_VIEW);

    Livewire::test(ListTenants::class)
        ->assertTableActionHidden('edit', $this->tenant);
});

test('the users tab lists only the tenant members with their tenant role and ownership', function () {
    tenantPageAdmin(AdminPermissions::TENANT_VIEW, AdminPermissions::USER_VIEW_ANY);

    $member = User::factory()->create(['email' => 'member@acme.test']);
    $this->tenant->members()->attach($member);
    app(AssignTenantRole::class)->handle($member, $this->tenant, TenantRoles::VIEW_ONLY);
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

    $outsider = $this->other->members()->first();
    $rows = $this->tenant->members()->get()->keyBy('email');
    $ownerRow = $rows['owner@acme.test'];
    $memberRow = $rows['member@acme.test'];

    Livewire::test(UsersRelationManager::class, ['ownerRecord' => $this->tenant, 'pageClass' => ViewTenant::class])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$ownerRow, $memberRow])
        ->assertCanNotSeeTableRecords([$outsider])
        ->assertTableColumnStateSet('tenant_roles', [TenantRoles::OWNER->value], $ownerRow)
        ->assertTableColumnStateSet('tenant_roles', [TenantRoles::VIEW_ONLY->value], $memberRow)
        ->assertTableColumnDoesNotExist('is_owner')
        ->assertTableFilterExists('is_owner')
        ->filterTable('role', TenantRoles::VIEW_ONLY->value)
        ->assertCanSeeTableRecords([$memberRow])
        ->assertCanNotSeeTableRecords([$ownerRow])
        ->removeTableFilters()
        ->filterTable('is_owner', true)
        ->assertCanSeeTableRecords([$ownerRow])
        ->assertCanNotSeeTableRecords([$memberRow]);
});

test('the users tab shows the membership status of each member in this tenant', function () {
    tenantPageAdmin(AdminPermissions::TENANT_VIEW, AdminPermissions::USER_VIEW_ANY);

    $member = User::factory()->create(['email' => 'inactive@acme.test']);
    $this->tenant->members()->attach($member, ['status' => MemberStatus::INACTIVE]);

    $rows = $this->tenant->members()->get()->keyBy('email');

    Livewire::test(UsersRelationManager::class, ['ownerRecord' => $this->tenant, 'pageClass' => ViewTenant::class])
        ->assertTableColumnStateSet('membership_status', MemberStatus::ACTIVE, $rows['owner@acme.test'])
        ->assertTableColumnStateSet('membership_status', MemberStatus::INACTIVE, $rows['inactive@acme.test']);
});

test('the users tab is hidden without the user view permission', function () {
    tenantPageAdmin(AdminPermissions::TENANT_VIEW);

    expect(UsersRelationManager::canViewForRecord($this->tenant, ViewTenant::class))->toBeFalse();

    tenantPageAdmin(AdminPermissions::TENANT_VIEW, AdminPermissions::USER_VIEW_ANY);

    expect(UsersRelationManager::canViewForRecord($this->tenant, ViewTenant::class))->toBeTrue();
});

test('an admin with the user create permission invites a user into the tenant from the users tab, with no inviter', function () {
    tenantPageAdmin(AdminPermissions::TENANT_VIEW, AdminPermissions::USER_VIEW_ANY);

    $manager = fn () => Livewire::test(UsersRelationManager::class, ['ownerRecord' => $this->tenant, 'pageClass' => ViewTenant::class]);

    $manager()->assertActionHidden(TestAction::make('create_invite')->table());

    tenantPageAdmin(AdminPermissions::TENANT_VIEW, AdminPermissions::USER_VIEW_ANY, AdminPermissions::USER_CREATE);
    $role = Role::findOrCreate(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);

    $manager()
        ->assertActionVisible(TestAction::make('create_invite')->table())
        ->callAction(TestAction::make('create_invite')->table(), ['email' => 'New@Acme.test', 'role_id' => $role->getKey()])
        ->assertHasNoActionErrors();

    $invitation = Invitation::query()->where('tenant_id', $this->tenant->getKey())->sole();

    expect($invitation->email)->toBe('new@acme.test')
        ->and($invitation->invited_by)->toBeNull()
        ->and($invitation->role_id)->toBe($role->getKey());
});

test('inviting an existing member from the users tab is refused with a message', function () {
    tenantPageAdmin(AdminPermissions::TENANT_VIEW, AdminPermissions::USER_VIEW_ANY, AdminPermissions::USER_CREATE);
    $role = Role::findOrCreate(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);

    Livewire::test(UsersRelationManager::class, ['ownerRecord' => $this->tenant, 'pageClass' => ViewTenant::class])
        ->callAction(TestAction::make('create_invite')->table(), ['email' => 'owner@acme.test', 'role_id' => $role->getKey()]);

    expect(Invitation::query()->count())->toBe(0);
});

test('the invite action is gone when the invitations feature is off', function () {
    config(['tenancy-foundation.features.invitations' => false]);
    tenantPageAdmin(AdminPermissions::TENANT_VIEW, AdminPermissions::USER_VIEW_ANY, AdminPermissions::USER_CREATE);

    Livewire::test(UsersRelationManager::class, ['ownerRecord' => $this->tenant, 'pageClass' => ViewTenant::class])
        ->assertActionHidden(TestAction::make('create_invite')->table());
});

test('a cancelled tenant can still be viewed and edited', function () {
    tenantPageAdmin(AdminPermissions::TENANT_VIEW, AdminPermissions::TENANT_UPDATE);
    app(CancelTenant::class)->handle($this->tenant);
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

    Livewire::test(ViewTenant::class, ['record' => $this->tenant->getRouteKey()])->assertSuccessful();
    Livewire::test(EditTenant::class, ['record' => $this->tenant->getRouteKey()])->assertSuccessful();
});

test('the page header edit and view actions are labelled buttons, not icon buttons', function () {
    tenantPageAdmin(AdminPermissions::TENANT_VIEW, AdminPermissions::TENANT_UPDATE);

    $view = Livewire::test(ViewTenant::class, ['record' => $this->tenant->getRouteKey()])->instance()->getAction('edit');
    $edit = Livewire::test(EditTenant::class, ['record' => $this->tenant->getRouteKey()])->instance()->getAction('view');

    foreach ([$view, $edit] as $action) {
        expect($action->isButton())->toBeTrue()
            ->and($action->isIconButton())->toBeFalse()
            ->and($action->isLabelHidden())->toBeFalse()
            ->and($action->getTooltip())->toBeNull();
    }
});

test('leaving an impersonation goes back to the page the admin started from, or the users list', function (bool $withReferer) {
    $page = TenantResource::getUrl('view', ['record' => $this->tenant]);

    $withReferer
        ? request()->headers->set('referer', $page)
        : request()->headers->remove('referer');

    expect(ImpersonateUserAction::make()->getBackTo())->toBe($withReferer ? $page : UserResource::getUrl('index'));
})->with([
    'with a referer' => true,
    'without a referer' => false,
]);

test('saving a tenant stays on the page by default and returns to the previous page when configured', function (bool $enabled, ?string $expected) {
    tenantPageAdmin(AdminPermissions::TENANT_VIEW, AdminPermissions::TENANT_UPDATE);
    config(['tenancy-foundation.redirects.edit_returns_to_previous' => $enabled]);
    session()->setPreviousUrl('https://example.test/admin/tenants?search=acme');

    $page = Livewire::test(EditTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->fillForm(['name' => 'Acme Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    $expected === null ? $page->assertNoRedirect() : $page->assertRedirect($expected);
})->with([
    'off' => [false, null],
    'on' => [true, 'https://example.test/admin/tenants?search=acme'],
]);
