<?php

use Illuminate\Support\Facades\Notification;
use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Notifications\Admin\AdminInvited;
use Spatie\Permission\Models\Role;

const CREATE_ADMIN_COMMAND = 'tenancy-foundation:create-admin';

beforeEach(function () {
    Notification::fake();
});

function createdAdmin(string $email): Admin
{
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

    return Admin::query()->where('email', $email)->firstOrFail();
}

it('asks for the email, the name and the role from the seeded roles', function () {
    $this->seed(AdminRolePermissionsSeeder::class);
    Role::findOrCreate('auditor', AuthGuard::ADMIN->value);

    $this->artisan(CREATE_ADMIN_COMMAND)
        ->expectsQuestion('Email address of the new admin', 'asked@example.com')
        ->expectsQuestion('Name', 'Asked Admin')
        ->expectsChoice('Role', AdminRoles::SUPPORT->value, [
            'admin' => 'Admin',
            'auditor' => 'Auditor',
            'super_admin' => 'Super Admin',
            'support' => 'Support',
        ])
        ->assertSuccessful();

    $admin = createdAdmin('asked@example.com');

    expect($admin->status)->toBe(AdminStatus::PENDING)
        ->and($admin->name)->toBe('Asked Admin')
        ->and($admin->hasRole(AdminRoles::SUPPORT))->toBeTrue();

    Notification::assertSentTo($admin, AdminInvited::class);
});

it('skips the role question when the role is given', function () {
    $this->seed(AdminRolePermissionsSeeder::class);

    $this->artisan(CREATE_ADMIN_COMMAND, ['email' => 'support@example.com', '--role' => AdminRoles::SUPPORT->value])
        ->expectsQuestion('Name', 'Support')
        ->assertSuccessful();

    expect(createdAdmin('support@example.com')->hasRole(AdminRoles::SUPPORT))->toBeTrue();
});

it('assigns an app defined admin role', function () {
    $this->seed(AdminRolePermissionsSeeder::class);
    Role::findOrCreate('auditor', AuthGuard::ADMIN->value);

    $this->artisan(CREATE_ADMIN_COMMAND, ['email' => 'audit@example.com', '--role' => 'auditor'])
        ->expectsQuestion('Name', 'Auditor')
        ->assertSuccessful();

    expect(createdAdmin('audit@example.com')->hasRole('auditor'))->toBeTrue();
});

it('refuses invalid input without creating an admin', function (string $email, string $role, bool $seedRoles) {
    if ($seedRoles) {
        $this->seed(AdminRolePermissionsSeeder::class);
    }

    Admin::factory()->create(['email' => 'taken@example.com'])->delete();

    $command = $this->artisan(CREATE_ADMIN_COMMAND, ['email' => $email, '--role' => $role]);

    if ($seedRoles) {
        $command->expectsQuestion('Name', 'Someone');
    }

    $command->assertFailed();

    expect(Admin::withTrashed()->count())->toBe(1);
    Notification::assertNothingSent();
})->with([
    'an invalid email' => ['not-an-email', AdminRoles::SUPER_ADMIN->value, true],
    'the email of a deleted admin' => ['taken@example.com', AdminRoles::SUPER_ADMIN->value, true],
    'an unknown role' => ['new@example.com', 'emperor', true],
    'roles not seeded yet' => ['new@example.com', AdminRoles::SUPER_ADMIN->value, false],
]);

it('lists the available roles when the role is unknown', function () {
    $this->seed(AdminRolePermissionsSeeder::class);

    $this->artisan(CREATE_ADMIN_COMMAND, ['email' => 'new@example.com', '--role' => 'emperor'])
        ->expectsQuestion('Name', 'Someone')
        ->expectsOutputToContain('Available roles: admin, super_admin, support.')
        ->assertFailed();
});
