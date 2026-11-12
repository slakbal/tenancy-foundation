<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Actions\Tenant\AddTenantOwner;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Database\Seeders\TenantRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantUserAbilities;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Policies\Tenant\UserPolicy;

function tenantUserWrites(): array
{
    return [TenantUserAbilities::RemoveMember, TenantUserAbilities::ChangeRole, TenantUserAbilities::Activate, TenantUserAbilities::Deactivate];
}

function policyRuleMember($tenant, TenantRoles $role): User
{
    $user = User::factory()->create();
    $tenant->members()->attach($user->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);
    app(AssignTenantRole::class)->handle($user, $tenant, $role);

    return $user;
}

beforeEach(function () {
    $this->seed(TenantRolePermissionsSeeder::class);

    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);
    setPermissionsTeamId($this->tenant->getKey());

    $this->admin = policyRuleMember($this->tenant, TenantRoles::ADMIN);
    $this->viewer = policyRuleMember($this->tenant, TenantRoles::VIEW_ONLY);
    $this->member = policyRuleMember($this->tenant, TenantRoles::VIEW_ONLY);
    $this->coOwner = policyRuleMember($this->tenant, TenantRoles::ADMIN);
    app(AddTenantOwner::class)->handle($this->tenant, $this->coOwner);

    $this->actingAs($this->owner, AuthGuard::TENANT->value);
    Filament::setTenant($this->tenant);
});

test('an admin manages members but never an owner, and cannot remove or re-role themselves', function () {
    foreach (['update', 'delete', ...tenantUserWrites()] as $ability) {
        $name = $ability instanceof BackedEnum ? $ability->value : $ability;

        expect($this->admin->can($ability, $this->member))->toBeTrue("admin may {$name} a member")
            ->and($this->admin->can($ability, $this->coOwner))->toBeFalse("admin must not {$name} an owner");
    }

    expect($this->admin->can(TenantUserAbilities::RemoveMember, $this->admin))->toBeFalse()
        ->and($this->admin->can(TenantUserAbilities::ChangeRole, $this->admin))->toBeFalse()
        ->and($this->admin->can('update', $this->admin))->toBeTrue();
});

test('a view only member can read users but write nothing', function () {
    expect($this->viewer->can('viewAny', User::class))->toBeTrue()
        ->and($this->viewer->can('view', $this->member))->toBeTrue();

    foreach (['create', 'update', 'delete', 'restore', ...tenantUserWrites()] as $ability) {
        $name = $ability instanceof BackedEnum ? $ability->value : $ability;
        $subject = $ability === 'create' ? User::class : $this->member;

        expect($this->viewer->can($ability, $subject))->toBeFalse("view only must not {$name}");
    }
});

test('anyone but an owner may leave, only for themselves', function () {
    expect($this->viewer->can(TenantUserAbilities::Leave, $this->viewer))->toBeTrue()
        ->and($this->admin->can(TenantUserAbilities::Leave, $this->admin))->toBeTrue()
        ->and($this->admin->can(TenantUserAbilities::Leave, $this->member))->toBeFalse()
        ->and($this->coOwner->isOwner($this->tenant))->toBeTrue();

    $policy = new UserPolicy;
    expect($policy->leave($this->coOwner, $this->coOwner))->toBeFalse();
});

test('nobody force deletes a user from the tenant panel, an owner included', function () {
    foreach ([$this->owner, $this->admin, $this->viewer] as $actor) {
        expect($actor->can('forceDelete', $this->member))->toBeFalse()
            ->and($actor->can('forceDeleteAny', User::class))->toBeFalse();
    }
});
