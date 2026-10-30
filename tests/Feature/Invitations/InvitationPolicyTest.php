<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\InvitationPermissions;
use Slakbal\TenancyFoundation\Models\Invitation;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->tenant = Tenant::factory()->create();
    setPermissionsTeamId($this->tenant->getKey());
});

function invitationUserWithPermission(Tenant $tenant, InvitationPermissions $permission): User
{
    setPermissionsTeamId($tenant->getKey());
    $user = User::factory()->create();
    Permission::findOrCreate($permission->value, AuthGuard::TENANT->value);
    $user->givePermissionTo($permission);

    return $user;
}

test('each invitation ability requires its permission', function (string $ability, InvitationPermissions $permission, bool $onRecord) {
    $subject = $onRecord ? Invitation::factory()->for($this->tenant, 'tenant')->create() : Invitation::class;

    $without = User::factory()->create();
    $with = invitationUserWithPermission($this->tenant, $permission);

    expect($without->can($ability, $subject))->toBeFalse();
    expect($with->can($ability, $subject))->toBeTrue();
})->with([
    'create' => ['create', InvitationPermissions::INVITATION_CREATE, false],
    'delete' => ['delete', InvitationPermissions::INVITATION_DELETE, true],
    'deleteAny' => ['deleteAny', InvitationPermissions::INVITATION_DELETE, false],
    'forceDelete' => ['forceDelete', InvitationPermissions::INVITATION_DELETE, true],
    'forceDeleteAny' => ['forceDeleteAny', InvitationPermissions::INVITATION_DELETE, false],
]);

test('viewing invitations also needs the create permission', function (string $ability, InvitationPermissions $viewPermission, bool $onRecord) {
    $subject = $onRecord ? Invitation::factory()->for($this->tenant, 'tenant')->create() : Invitation::class;

    $viewOnly = invitationUserWithPermission($this->tenant, $viewPermission);
    $inviter = invitationUserWithPermission($this->tenant, $viewPermission);
    Permission::findOrCreate(InvitationPermissions::INVITATION_CREATE->value, AuthGuard::TENANT->value);
    $inviter->givePermissionTo(InvitationPermissions::INVITATION_CREATE);

    expect($viewOnly->can($ability, $subject))->toBeFalse()
        ->and($inviter->can($ability, $subject))->toBeTrue();
})->with([
    'viewAny' => ['viewAny', InvitationPermissions::INVITATION_VIEW_ANY, false],
    'view' => ['view', InvitationPermissions::INVITATION_VIEW, true],
]);

test('delete is denied once an invitation has been accepted, even with INVITATION_DELETE', function () {
    $invitation = Invitation::factory()->for($this->tenant, 'tenant')->create([
        'accepted_at' => now(),
    ]);

    $with = invitationUserWithPermission($this->tenant, InvitationPermissions::INVITATION_DELETE);

    expect($with->can('delete', $invitation))->toBeFalse();
});
