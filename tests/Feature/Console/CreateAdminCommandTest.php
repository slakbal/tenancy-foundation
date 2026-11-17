<?php

use Illuminate\Support\Facades\Notification;
use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Notifications\Admin\AdminInvited;

const CREATE_ADMIN_COMMAND = 'tenancy-foundation:create-admin';

beforeEach(function () {
    Notification::fake();
});

it('creates a pending super admin and mails the invitation', function () {
    $this->seed(AdminRolePermissionsSeeder::class);

    $this->artisan(CREATE_ADMIN_COMMAND, ['email' => 'first@example.com'])
        ->expectsQuestion('Name', 'First Admin')
        ->assertSuccessful();

    $admin = Admin::query()->where('email', 'first@example.com')->firstOrFail();
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

    expect($admin->status)->toBe(AdminStatus::PENDING)
        ->and($admin->name)->toBe('First Admin')
        ->and($admin->hasRole(AdminRoles::SUPER_ADMIN))->toBeTrue();

    Notification::assertSentTo($admin, AdminInvited::class);
});

it('assigns the given role', function () {
    $this->seed(AdminRolePermissionsSeeder::class);

    $this->artisan(CREATE_ADMIN_COMMAND, ['email' => 'support@example.com', '--role' => AdminRoles::SUPPORT->value])
        ->expectsQuestion('Name', 'Support')
        ->assertSuccessful();

    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

    expect(Admin::query()->where('email', 'support@example.com')->firstOrFail()->hasRole(AdminRoles::SUPPORT))->toBeTrue();
});

it('refuses invalid input without creating an admin', function (string $email, string $role, bool $seedRoles) {
    if ($seedRoles) {
        $this->seed(AdminRolePermissionsSeeder::class);
    }

    Admin::factory()->create(['email' => 'taken@example.com'])->delete();

    $this->artisan(CREATE_ADMIN_COMMAND, ['email' => $email, '--role' => $role])
        ->expectsQuestion('Name', 'Someone')
        ->assertFailed();

    expect(Admin::withTrashed()->count())->toBe(1);
    Notification::assertNothingSent();
})->with([
    'an invalid email' => ['not-an-email', AdminRoles::SUPER_ADMIN->value, true],
    'the email of a deleted admin' => ['taken@example.com', AdminRoles::SUPER_ADMIN->value, true],
    'an unknown role' => ['new@example.com', 'emperor', true],
    'roles not seeded yet' => ['new@example.com', AdminRoles::SUPER_ADMIN->value, false],
]);
