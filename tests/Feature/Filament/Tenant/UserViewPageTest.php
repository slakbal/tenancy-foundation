<?php


use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Database\Seeders\TenantRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\ListUsers;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\ViewUser;
use Slakbal\TenancyFoundation\Models\User;

beforeEach(function () {
    $this->seed(TenantRolePermissionsSeeder::class);

    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);
    setPermissionsTeamId($this->tenant->getKey());

    $this->member = User::factory()->create(['last_login_at' => now()->subDay()]);
    $this->tenant->members()->attach($this->member->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);
    app(AssignTenantRole::class)->handle($this->member, $this->tenant, TenantRoles::ADMIN);

    // users have to exist before the panel tenant is set, or Filament attaches every new user to it
    $this->viewer = User::factory()->create();
    $this->tenant->members()->attach($this->viewer->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);
    app(AssignTenantRole::class)->handle($this->viewer, $this->tenant, TenantRoles::VIEW_ONLY);

    $this->outsider = User::factory()->create();
    app(CreateTenant::class)->handle(['name' => 'Other', 'subdomain' => 'other'], $this->outsider);
    setPermissionsTeamId($this->tenant->getKey());

    $this->actingAs($this->owner, AuthGuard::TENANT->value);
    Filament::setTenant($this->tenant);
});

test('the view page shows the name, email, tenant role, owner flag, joined and last login', function () {
    Livewire::test(ViewUser::class, ['record' => $this->member->getRouteKey()])
        ->assertSuccessful()
        ->assertSchemaStateSet(['name' => $this->member->name, 'email' => $this->member->email])
        ->assertSchemaComponentExists('tenant_role')
        ->assertSchemaComponentExists('is_owner')
        ->assertSchemaComponentExists('joined_at')
        ->assertSchemaComponentExists('last_login_at')
        ->assertSee(TenantRoles::ADMIN->getLabel());
});

test('the users list links to the view page, and a user of another tenant cannot be opened', function () {
    Livewire::test(ListUsers::class)
        ->assertTableActionVisible('view', $this->member)
        ->assertCanSeeTableRecords([$this->member])
        ->assertCanNotSeeTableRecords([$this->outsider]);

    Livewire::test(ViewUser::class, ['record' => $this->outsider->getRouteKey()])->assertNotFound();
});

test('a view only member can open the view page but gets no edit or remove action', function () {
    $this->actingAs($this->viewer, AuthGuard::TENANT->value);

    Livewire::test(ViewUser::class, ['record' => $this->member->getRouteKey()])
        ->assertSuccessful()
        ->assertActionHidden('edit')
        ->assertActionHidden('removeFromTenant');
});
