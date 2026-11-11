<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Invitation\AcceptInvitation;
use Slakbal\TenancyFoundation\Actions\Invitation\CreateInvitation;
use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\RemoveTenantMember;
use Slakbal\TenancyFoundation\Database\Seeders\TenantRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Enums\MemberStatus;
use Slakbal\TenancyFoundation\Exceptions\InvalidInvitation;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\ListUsers;
use Slakbal\TenancyFoundation\Models\Role;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Support\UserTenantRoles;

beforeEach(function () {
    Notification::fake();
    $this->seed(TenantRolePermissionsSeeder::class);

    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);
    $this->other = app(CreateTenant::class)->handle(['name' => 'Other', 'subdomain' => 'other'], User::factory()->create());
    setPermissionsTeamId($this->tenant->getKey());

    $this->member = User::factory()->create();
    $this->tenant->members()->attach($this->member->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);
    $this->other->members()->attach($this->member->getKey(), ['is_owner' => false, 'is_default_tenant' => false]);
    app(AssignTenantRole::class)->handle($this->member, $this->tenant, TenantRoles::ADMIN);
});

test('a member who was removed from one of their tenants rejoins through a new invitation with the new role and keeps their default tenant', function () {
    app(RemoveTenantMember::class)->handle($this->tenant, $this->member);

    $role = Role::findByName(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);
    $invitation = app(CreateInvitation::class)->handle($this->tenant, $this->owner, $this->member->email, $role);

    app(AcceptInvitation::class)->handle($invitation, [], $this->member->fresh());

    $member = $this->member->fresh();
    $membership = $member->tenants()->get()->firstWhere('id', $this->tenant->getKey());

    expect($membership->pivot->status)->toBe(MemberStatus::ACTIVE)
        ->and((bool) $membership->pivot->is_owner)->toBeFalse()
        ->and((bool) $membership->pivot->is_default_tenant)->toBeFalse()
        ->and($member->tenants()->count())->toBe(2)
        ->and((new UserTenantRoles($member))->for($this->tenant)->all())->toBe([TenantRoles::VIEW_ONLY->value]);
});

test('a deleted user cannot be invited back but is restored into the tenant with a role', function () {
    $single = User::factory()->create();
    $this->tenant->members()->attach($single->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);
    app(AssignTenantRole::class)->handle($single, $this->tenant, TenantRoles::ADMIN);

    expect(app(RemoveTenantMember::class)->handle($this->tenant, $single))->toBeTrue();

    $role = Role::findByName(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);
    expect(fn () => app(CreateInvitation::class)->handle($this->tenant, $this->owner, $single->email, $role))->toThrow(InvalidInvitation::class);

    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();
    $this->actingAs($this->owner, AuthGuard::TENANT->value);
    Filament::setTenant($this->tenant);

    Livewire::test(ListUsers::class)
        ->filterTable('trashed', true)
        ->assertTableActionVisible('restore', $single)
        ->callTableAction('restore', $single, ['role' => TenantRoles::VIEW_ONLY->value])
        ->assertNotified();

    $restored = User::find($single->getKey());
    $membership = $restored->tenants()->get()->firstWhere('id', $this->tenant->getKey());

    expect($restored)->not->toBeNull()
        ->and($membership->pivot->status)->toBe(MemberStatus::ACTIVE)
        ->and((new UserTenantRoles($restored))->for($this->tenant)->all())->toBe([TenantRoles::VIEW_ONLY->value])
        ->and($restored->canAccessTenant($this->tenant))->toBeTrue();
});
