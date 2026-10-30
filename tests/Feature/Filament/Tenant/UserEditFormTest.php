<?php

use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\UpdateTenantUser;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\EditUser;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Permission;

function tenantMember($tenant, array $permissions = [], bool $owner = false): User
{
    $user = User::factory()->create();
    $tenant->members()->syncWithoutDetaching([$user->getKey() => ['is_owner' => $owner, 'is_default_tenant' => true]]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission->value, AuthGuard::TENANT->value);
        $user->givePermissionTo($permission);
    }

    return $user;
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);
    setPermissionsTeamId($this->tenant->getKey());

    $this->member = tenantMember($this->tenant);

    $this->actingAs($this->owner, AuthGuard::TENANT->value);
    Filament::setTenant($this->tenant);
});

test('the name and the roles of a member are saved, the email is not editable', function () {
    $email = $this->member->email;

    Livewire::test(EditUser::class, ['record' => $this->member->getRouteKey()])
        ->assertFormFieldDisabled('email')
        ->fillForm(['name' => 'Renamed', 'tenant_roles' => ['view_only']])
        ->call('save')
        ->assertHasNoFormErrors();

    setPermissionsTeamId($this->tenant->getKey());
    $member = $this->member->fresh();

    expect($member->name)->toBe('Renamed')
        ->and($member->email)->toBe($email)
        ->and($member->roles->pluck('name')->all())->toBe(['view_only']);
});

test('the roles field is hidden for owners and yourself and a shared user keeps their name', function () {
    $coOwner = tenantMember($this->tenant, owner: true);
    $other = app(CreateTenant::class)->handle(['name' => 'Other', 'subdomain' => 'other'], User::factory()->create());
    $other->members()->syncWithoutDetaching([$this->member->getKey() => ['is_owner' => false, 'is_default_tenant' => false]]);
    setPermissionsTeamId($this->tenant->getKey());

    Livewire::test(EditUser::class, ['record' => $coOwner->getRouteKey()])->assertFormFieldHidden('tenant_roles');
    Livewire::test(EditUser::class, ['record' => $this->owner->getRouteKey()])->assertFormFieldHidden('tenant_roles');
    Livewire::test(EditUser::class, ['record' => $this->member->getRouteKey()])
        ->assertFormFieldVisible('tenant_roles')
        ->assertFormFieldDisabled('name');
});

test('only members with the update permission can open the edit page', function () {
    $viewer = tenantMember($this->tenant, [TenantPermissions::USER_VIEW_ANY, TenantPermissions::USER_VIEW]);
    $this->actingAs($viewer, AuthGuard::TENANT->value);

    Livewire::test(EditUser::class, ['record' => $this->member->getRouteKey()])->assertForbidden();
});

test('the update action refuses to change the roles of an owner or of the actor', function () {
    $action = app(UpdateTenantUser::class);

    expect(fn () => $action->handle($this->owner, $this->tenant, ['tenant_roles' => ['view_only']]))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $action->handle($this->member, $this->tenant, ['tenant_roles' => ['view_only']], $this->member))
        ->toThrow(AuthorizationException::class);
});
