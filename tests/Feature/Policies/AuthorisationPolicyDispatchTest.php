<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\InvitationPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Models\Invitation;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Permission;

test('an ability with no mapped permission at all resolves false rather than throwing (e.g. TenantPolicy has no restore)', function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $tenant = Tenant::factory()->create();
    setPermissionsTeamId($tenant->getKey());
    $owner = User::factory()->create();
    $tenant->members()->attach($owner, ['is_owner' => true, 'is_default_tenant' => true]);

    expect($owner->can('restore', $tenant))->toBeFalse();
});

test('record abilities in a tenant panel require the record to belong to the current tenant', function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    setPermissionsTeamId($tenant->getKey());

    $member = User::factory()->create();
    $tenant->members()->attach($member, ['is_owner' => false, 'is_default_tenant' => true]);
    $ownInvitation = Invitation::factory()->create(['tenant_id' => $tenant->getKey()]);
    $foreignInvitation = Invitation::factory()->create(['tenant_id' => $otherTenant->getKey()]);
    Filament::setTenant($tenant, isQuiet: true);

    foreach ([InvitationPermissions::INVITATION_VIEW, InvitationPermissions::INVITATION_CREATE, InvitationPermissions::INVITATION_DELETE, TenantPermissions::TENANT_VIEW] as $permission) {
        Permission::findOrCreate($permission->value, AuthGuard::TENANT->value);
        $member->givePermissionTo($permission);
    }

    expect($member->can('view', $ownInvitation))->toBeTrue()
        ->and($member->can('delete', $ownInvitation))->toBeTrue()
        ->and($member->can('view', $foreignInvitation))->toBeFalse()
        ->and($member->can('delete', $foreignInvitation))->toBeFalse()
        ->and($member->can('view', $tenant))->toBeTrue()
        ->and($member->can('view', $otherTenant))->toBeFalse();
});
