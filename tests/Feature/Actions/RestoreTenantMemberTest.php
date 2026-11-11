<?php

use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\RemoveTenantMember;
use Slakbal\TenancyFoundation\Actions\Tenant\RestoreTenantMember;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Enums\MemberStatus;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Support\UserTenantRoles;

beforeEach(function () {
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], User::factory()->create());
    setPermissionsTeamId($this->tenant->getKey());

    $this->member = User::factory()->create();
    $this->tenant->members()->attach($this->member, ['is_owner' => false, 'is_default_tenant' => true]);
    app(AssignTenantRole::class)->handle($this->member, $this->tenant, TenantRoles::ADMIN);
});

test('restoring a deleted sole tenant user reactivates the membership and gives the fallback role they lost on removal', function () {
    app(RemoveTenantMember::class)->handle($this->tenant, $this->member);
    $this->tenant->members()->updateExistingPivot($this->member->getKey(), ['status' => MemberStatus::INACTIVE]);

    $user = User::withTrashed()->find($this->member->getKey());
    app(RestoreTenantMember::class)->handle($this->tenant, $user, TenantRoles::VIEW_ONLY);

    $membership = User::find($this->member->getKey())->tenants()->get()->firstWhere('id', $this->tenant->getKey());

    expect($membership->pivot->status)->toBe(MemberStatus::ACTIVE)
        ->and((new UserTenantRoles(User::find($this->member->getKey())))->for($this->tenant)->all())->toBe([TenantRoles::VIEW_ONLY->value]);
});

test('restoring keeps roles that are still there instead of replacing them', function () {
    $this->member->delete();

    app(RestoreTenantMember::class)->handle($this->tenant, User::withTrashed()->find($this->member->getKey()), TenantRoles::VIEW_ONLY);

    expect((new UserTenantRoles(User::find($this->member->getKey())))->for($this->tenant)->all())->toBe([TenantRoles::ADMIN->value]);
});

test('restoring a user who was detached re-attaches them as a non owner and makes it their default when they have no other tenant', function () {
    $this->tenant->members()->detach($this->member->getKey());

    app(RestoreTenantMember::class)->handle($this->tenant, $this->member->fresh(), TenantRoles::VIEW_ONLY);

    $membership = $this->member->fresh()->tenants()->get()->firstWhere('id', $this->tenant->getKey());

    expect((bool) $membership->pivot->is_owner)->toBeFalse()
        ->and((bool) $membership->pivot->is_default_tenant)->toBeTrue()
        ->and($membership->pivot->status)->toBe(MemberStatus::ACTIVE);
});
