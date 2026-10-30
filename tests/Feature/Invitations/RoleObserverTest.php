<?php

use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Models\Invitation;
use Slakbal\TenancyFoundation\Models\Role;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;

test('deleting a role only cancels the pending, unexpired invitations that carry it', function (bool $accepted, bool $expired, bool $onDeletedRole, bool $survives, bool $roleCleared) {
    $tenant = Tenant::factory()->create();
    $inviter = User::factory()->create();
    $roleToDelete = Role::findOrCreate(TenantRoles::ADMIN->value, AuthGuard::TENANT->value);
    $otherRole = Role::findOrCreate(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);

    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'invited_by' => $inviter->id,
        'role_id' => ($onDeletedRole ? $roleToDelete : $otherRole)->id,
        'accepted_at' => $accepted ? now() : null,
        'expires_at' => $expired ? now()->subDay() : now()->addDays(7),
    ]);

    $roleToDelete->delete();

    if (! $survives) {
        expect(Invitation::find($invitation->id))->toBeNull();

        return;
    }

    expect(Invitation::find($invitation->id))->not->toBeNull();

    if ($roleCleared) {
        expect($invitation->refresh()->role_id)->toBeNull();
    }
})->with([
    'pending invitation of the deleted role is cancelled' => [false, false, true, false, false],
    'accepted invitation stays with its role cleared' => [true, false, true, true, true],
    'invitation of a different role is untouched' => [false, false, false, true, false],
    'already expired pending invitation is left to pruning' => [false, true, true, true, false],
]);
