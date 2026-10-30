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

function invitationMember(array $permissions): array
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

beforeEach(function () {
    [$this->tenant, $this->admin] = invitationMember([InvitationPermissions::INVITATION_VIEW_ANY, InvitationPermissions::INVITATION_DELETE]);
});

test('the invitations table lists only the current tenant invitations', function () {
    $mine = Invitation::factory()->for($this->tenant, 'tenant')->create();
    $theirs = Invitation::withoutEvents(
        fn () => Invitation::factory()->for(Tenant::factory(), 'tenant')->create()
    );

    Livewire::test(ListInvitations::class)
        ->loadTable()
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

test('revoking a pending invitation deletes it', function () {
    $invitation = Invitation::factory()->for($this->tenant, 'tenant')->create([
        'accepted_at' => null,
    ]);

    Livewire::test(ListInvitations::class)
        ->callTableAction('revoke', $invitation)
        ->assertHasNoTableActionErrors();

    expect(Invitation::query()->whereKey($invitation->getKey())->exists())->toBeFalse();
});

test('the revoke row action is only visible for a pending invitation', function (bool $accepted, bool $visible) {
    $invitation = Invitation::factory()->for($this->tenant, 'tenant')->create([
        'accepted_at' => $accepted ? now() : null,
    ]);

    $component = Livewire::test(ListInvitations::class);

    $visible
        ? $component->assertTableActionVisible('revoke', $invitation)
        : $component->assertTableActionHidden('revoke', $invitation);
})->with([
    'pending' => [false, true],
    'accepted' => [true, false],
]);

test('a user without INVITATION_VIEW_ANY is forbidden from the invitations list', function () {
    $outsider = User::factory()->create();
    $this->tenant->members()->syncWithoutDetaching([$outsider->getKey() => ['is_owner' => false, 'is_default_tenant' => true]]);
    $this->actingAs($outsider, AuthGuard::TENANT->value);

    Livewire::test(ListInvitations::class)->assertForbidden();
});

test('the invited by column still shows an inviter who has left the tenant, without a tenant subquery', function () {
    $inviter = User::factory()->create(['name' => 'Gone Inviter']);
    $this->tenant->members()->detach($inviter);
    $invitation = Invitation::factory()->for($this->tenant, 'tenant')->create(['invited_by' => $inviter->id]);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    Livewire::test(ListInvitations::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$invitation])
        ->assertSee('Gone Inviter');

    $creatorQueries = array_filter($queries, fn (string $sql): bool => str_starts_with($sql, 'select "id", "name" from "users"'));

    expect($creatorQueries)->not->toBeEmpty()
        ->and(collect($creatorQueries)->filter(fn (string $sql): bool => str_contains($sql, 'tenant_user')))->toBeEmpty();
});
