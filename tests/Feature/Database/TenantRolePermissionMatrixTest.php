<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Database\Seeders\TenantRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\InvitationPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Models\Invitation;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(TenantRolePermissionsSeeder::class);
});

function matrixPermissionsOf(TenantRoles $role): array
{
    return Role::findByName($role->value, AuthGuard::TENANT->value)->permissions->pluck('name')->all();
}

dataset('every tenant permission', fn () => collect([...TenantPermissions::cases(), ...InvitationPermissions::cases()])
    ->mapWithKeys(fn (BackedEnum $permission): array => [$permission->value => [$permission]])
    ->all());

test('a permission is held by the admin only, except the two user view permissions the view only role also holds', function (BackedEnum $permission) {
    $viewOnly = [TenantPermissions::USER_VIEW_ANY, TenantPermissions::USER_VIEW];

    expect(matrixPermissionsOf(TenantRoles::OWNER))->not->toContain($permission->value)
        ->and(matrixPermissionsOf(TenantRoles::ADMIN))->toContain($permission->value)
        ->and(in_array($permission->value, matrixPermissionsOf(TenantRoles::VIEW_ONLY), true))->toBe(in_array($permission, $viewOnly, true));
})->with('every tenant permission');

test('a view only member can read users and nothing else on any tenant resource, an admin writes users but never force deletes', function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], User::factory()->create());
    setPermissionsTeamId($tenant->getKey());

    $viewer = User::factory()->create();
    $admin = User::factory()->create();
    $target = User::factory()->create();
    foreach ([$viewer, $admin, $target] as $user) {
        $tenant->members()->attach($user->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);
    }
    app(AssignTenantRole::class)->handle($viewer, $tenant, TenantRoles::VIEW_ONLY);
    app(AssignTenantRole::class)->handle($admin, $tenant, TenantRoles::ADMIN);

    $subjectInvitation = Invitation::factory()->for($tenant, 'tenant')->create();
    test()->actingAs($admin, AuthGuard::TENANT->value);
    Filament::setTenant($tenant);

    $writes = ['create', 'update', 'delete', 'deleteAny', 'restore', 'restoreAny', 'forceDelete', 'forceDeleteAny'];
    $subjects = [
        'user' => $target,
        'tenant' => $tenant,
        'invitation' => $subjectInvitation,
    ];

    foreach ($subjects as $name => $subject) {
        foreach ($writes as $ability) {
            expect($viewer->can($ability, $subject))->toBeFalse("view only must not {$ability} a {$name}");
        }

        expect($viewer->can('view', $subject))->toBe($name === 'user');
    }

    expect($admin->can('update', $target))->toBeTrue()
        ->and($admin->can('delete', $target))->toBeTrue()
        ->and($admin->can('update', $tenant))->toBeTrue();
});
