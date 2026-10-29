<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Policies\Tenant\UserPolicy;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();
});

test('the real Gate dispatches to an overridden ability, not the base permission-map method', function () {
    $tenant = Tenant::factory()->create();
    setPermissionsTeamId($tenant->getKey());
    Permission::findOrCreate(TenantPermissions::USER_DELETE->value, AuthGuard::TENANT->value);

    $actor = User::factory()->create();
    $actor->givePermissionTo(TenantPermissions::USER_DELETE);
    $tenant->members()->attach($actor, ['is_owner' => true, 'is_default_tenant' => true]);

    $override = new class extends UserPolicy
    {
        public function delete($actor, $target): bool
        {
            return ! $actor->is($target) && $actor->can(TenantPermissions::USER_DELETE);
        }
    };

    Gate::policy(User::class, $override::class);
    app()->instance($override::class, $override);

    expect($actor->can('delete', $actor))->toBeFalse();
    expect($actor->can('delete', User::factory()->create()))->toBeTrue();
});
