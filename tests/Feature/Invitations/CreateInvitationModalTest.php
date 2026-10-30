<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\InvitationPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Invitations\Pages\ListInvitations;
use Slakbal\TenancyFoundation\Models\Invitation;
use Slakbal\TenancyFoundation\Models\Role;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Permission;

function modalInvitationMember(array $permissions): array
{
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->members()->attach($user, ['is_owner' => false, 'is_default_tenant' => true]);

    setPermissionsTeamId($tenant->getKey());

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission->value, AuthGuard::TENANT->value);
    }

    $user->givePermissionTo($permissions);

    test()->actingAs($user, AuthGuard::TENANT->value);
    Filament::setTenant($tenant);

    return [$tenant, $user];
}

test('the create-invite modal lets a member with the invite permission pick a role, without repeated role queries, and sends the invitation with it', function () {
    [$tenant] = modalInvitationMember([InvitationPermissions::INVITATION_CREATE, InvitationPermissions::INVITATION_VIEW_ANY]);

    $role = Role::findOrCreate(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);

    $roleOptionQueries = 0;
    DB::listen(function ($query) use (&$roleOptionQueries): void {
        if (str_starts_with($query->sql, 'select * from "roles" where "guard_name" = ?')) {
            $roleOptionQueries++;
        }
    });

    Livewire::test(ListInvitations::class)
        ->mountAction('create_invite')
        ->setActionData([
            'email' => 'modal-invitee@example.com',
            'role_id' => $role->id,
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($roleOptionQueries)->toBeLessThanOrEqual(2);

    $invitation = Invitation::where('email', 'modal-invitee@example.com')->first();

    expect($invitation)->not->toBeNull();
    expect($invitation->role_id)->toBe($role->id);
    expect($invitation->tenant_id)->toBe($tenant->id);
});
