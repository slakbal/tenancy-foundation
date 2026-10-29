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

it('lets an active admin access the admin panel', function () {
    expect(Admin::factory()->create()->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('refuses an inactive admin', function () {
    expect(Admin::factory()->inactive()->create()->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('lets a pending admin pass so the password reset link works', function () {
    expect(Admin::factory()->pending()->create()->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('refuses a soft deleted admin', function () {
    $admin = Admin::factory()->create();
    $admin->delete();

    expect($admin->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

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
