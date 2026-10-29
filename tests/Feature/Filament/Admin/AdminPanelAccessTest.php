<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\UserResource;
use Slakbal\TenancyFoundation\Models\Admin;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('decides panel access from the admin state', function (string $state, bool $canAccess) {
    $admin = match ($state) {
        'active' => Admin::factory()->create(),
        'inactive' => Admin::factory()->inactive()->create(),
        'pending' => Admin::factory()->pending()->create(),
        'soft deleted' => tap(Admin::factory()->create())->delete(),
    };

    expect($admin->canAccessPanel(Filament::getPanel('admin')))->toBe($canAccess);
})->with([
    'an active admin gets in' => ['active', true],
    'an inactive admin is refused' => ['inactive', false],
    'a pending admin passes so the password reset link works' => ['pending', true],
    'a soft deleted admin is refused' => ['soft deleted', false],
]);

it('refuses an already signed in admin on the next request once deactivated', function () {
    setPermissionsTeamId(config('tenancy-foundation.admin_permissions_team_id'));
    $admin = Admin::factory()->create();
    Role::findOrCreate(AdminRoles::SUPER_ADMIN->value, AuthGuard::ADMIN->value);
    $admin->assignRole(AdminRoles::SUPER_ADMIN);
    $url = UserResource::getUrl('index');

    $this->actingAs($admin, 'admin')->get($url)->assertSuccessful();

    $admin->forceFill(['status' => AdminStatus::INACTIVE])->save();

    $this->actingAs($admin->fresh(), 'admin')->get($url)->assertForbidden();
});
