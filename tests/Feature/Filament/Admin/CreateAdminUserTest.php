<?php

use Filament\Actions\Testing\TestAction;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\CreateAdminUser;
use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Events\AdminUserCreated;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages\CreateAdminUser as CreateAdminUserPage;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages\ListAdminUsers;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Notifications\AdminInvited;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function creatingAdmin(AdminRoles $role = AdminRoles::ADMIN, bool $mayCreate = true): Admin
{
    foreach (AdminRoles::cases() as $case) {
        Role::findOrCreate($case->value, AuthGuard::ADMIN->value);
    }

    $admin = Admin::factory()->create();
    $admin->assignRole($role);

    if ($mayCreate) {
        foreach ([AdminPermissions::ADMIN_USER_VIEW_ANY, AdminPermissions::ADMIN_USER_VIEW, AdminPermissions::ADMIN_USER_CREATE] as $permission) {
            Permission::findOrCreate($permission->value, AuthGuard::ADMIN->value);
            $admin->givePermissionTo($permission);
        }
    }

    test()->actingAs($admin, AuthGuard::ADMIN->value);

    return $admin;
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(config('tenancy-foundation.admin_permissions_team_id'));
});

it('creates a pending admin with the role and an unknown password', function () {
    Event::fake([AdminUserCreated::class]);
    $actor = creatingAdmin();

    $admin = app(CreateAdminUser::class)->handle(['name' => 'New Admin', 'email' => 'new@example.com', 'role' => AdminRoles::SUPPORT->value], $actor);

    expect($admin->fresh()->status)->toBe(AdminStatus::PENDING)
        ->and($admin->name)->toBe('New Admin')
        ->and($admin->hasRole(AdminRoles::SUPPORT))->toBeTrue()
        ->and($admin->password)->not->toBeEmpty();

    Event::assertDispatched(AdminUserCreated::class, fn (AdminUserCreated $event): bool => $event->admin->is($admin));
});

it('refuses to let a non super admin create a super admin', function () {
    $actor = creatingAdmin();

    expect(fn () => app(CreateAdminUser::class)->handle(['name' => 'X', 'email' => 'x@example.com', 'role' => AdminRoles::SUPER_ADMIN->value], $actor))
        ->toThrow(AuthorizationException::class);

    expect(Admin::query()->where('email', 'x@example.com')->exists())->toBeFalse();
});

it('creates an admin through the create page and queues the invitation', function () {
    Notification::fake();
    creatingAdmin();

    Livewire::test(CreateAdminUserPage::class)
        ->fillForm(['name' => 'Page Admin', 'email' => 'page@example.com', 'role' => AdminRoles::SUPPORT->value])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    $admin = Admin::query()->where('email', 'page@example.com')->firstOrFail();

    expect($admin->status)->toBe(AdminStatus::PENDING);
    Notification::assertSentTo($admin, AdminInvited::class);
});

it('validates the create form', function () {
    creatingAdmin();
    $existing = Admin::factory()->create();

    Livewire::test(CreateAdminUserPage::class)
        ->fillForm(['name' => '', 'email' => $existing->email, 'role' => null])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'email' => 'unique', 'role' => 'required']);
});

it('points to the deleted admin when the email belongs to one', function () {
    creatingAdmin();
    $deleted = Admin::factory()->create();
    $deleted->delete();

    Livewire::test(CreateAdminUserPage::class)
        ->fillForm(['name' => 'Again', 'email' => $deleted->email, 'role' => AdminRoles::SUPPORT->value])
        ->call('create')
        ->assertHasFormErrors(['email' => 'This email belongs to a deleted admin. Restore that admin instead.']);
});

it('does not offer the super admin role to a non super admin on the create form', function () {
    creatingAdmin();

    Livewire::test(CreateAdminUserPage::class)
        ->assertFormFieldExists('role', fn ($field): bool => ! array_key_exists(AdminRoles::SUPER_ADMIN->value, $field->getOptions()));
});

it('shows the create button only to admins who may create admins', function () {
    creatingAdmin();
    Livewire::test(ListAdminUsers::class)->assertActionVisible('create');

    creatingAdmin(mayCreate: false);
    Livewire::test(CreateAdminUserPage::class)->assertForbidden();
});

it('sends no invitation when the notification is switched off', function () {
    Notification::fake();
    config(['tenancy-foundation.notifications.admin_invited' => null]);
    $actor = creatingAdmin();

    app(CreateAdminUser::class)->handle(['name' => 'Quiet', 'email' => 'quiet@example.com', 'role' => AdminRoles::SUPPORT->value], $actor);

    Notification::assertNothingSent();
});

it('puts a working set password link in the invitation and activates the admin once used', function () {
    $admin = Admin::factory()->pending()->create();

    $mail = (new AdminInvited)->toMail($admin);
    $url = $mail->actionUrl;

    expect($mail->actionText)->toBe('Set your password')
        ->and($url)->toContain('/admin/password-reset/reset')->toContain(urlencode($admin->email));

    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    Livewire::test(ResetPassword::class, ['email' => $query['email'], 'token' => $query['token']])
        ->fillForm(['password' => 'a-brand-new-password-1', 'passwordConfirmation' => 'a-brand-new-password-1'])
        ->call('resetPassword')
        ->assertHasNoFormErrors();

    expect($admin->fresh()->status)->toBe(AdminStatus::ACTIVE);
});

function resendingAdmin(bool $mayUpdate = true): Admin
{
    $admin = creatingAdmin();

    if ($mayUpdate) {
        Permission::findOrCreate(AdminPermissions::ADMIN_USER_UPDATE->value, AuthGuard::ADMIN->value);
        $admin->givePermissionTo(AdminPermissions::ADMIN_USER_UPDATE);
    }

    return $admin;
}

it('resends the invitation to a pending admin from the table with a fresh link', function () {
    resendingAdmin();
    $pending = Admin::factory()->pending()->create();
    $token = Password::broker('admins')->createToken($pending);

    Notification::fake();

    Livewire::test(ListAdminUsers::class)
        ->callAction(TestAction::make('resendInvitation')->table($pending))
        ->assertNotified();

    Notification::assertSentTo($pending, AdminInvited::class);

    $mail = (new AdminInvited)->toMail($pending);
    parse_str(parse_url($mail->actionUrl, PHP_URL_QUERY), $query);

    expect($query['token'])->not->toBe($token)
        ->and(Password::broker('admins')->tokenExists($pending, $token))->toBeFalse();
});

it('hides the resend action for admins who are not pending', function () {
    resendingAdmin();
    $active = Admin::factory()->create();

    Livewire::test(ListAdminUsers::class)
        ->assertActionHidden(TestAction::make('resendInvitation')->table($active));
});

it('hides the resend action when invitations are switched off', function () {
    resendingAdmin();
    config(['tenancy-foundation.notifications.admin_invited' => null]);
    $pending = Admin::factory()->pending()->create();

    Livewire::test(ListAdminUsers::class)
        ->assertActionHidden(TestAction::make('resendInvitation')->table($pending));
});

it('does not let an admin without update permission resend an invitation', function () {
    resendingAdmin(mayUpdate: false);
    $pending = Admin::factory()->pending()->create();

    Livewire::test(ListAdminUsers::class)
        ->assertActionHidden(TestAction::make('resendInvitation')->table($pending));
});

it('rejects an older invitation link once a new one has been sent', function () {
    $admin = Admin::factory()->pending()->create();

    $oldMail = (new AdminInvited)->toMail($admin);
    parse_str(parse_url($oldMail->actionUrl, PHP_URL_QUERY), $old);

    (new AdminInvited)->toMail($admin);

    Livewire::test(ResetPassword::class, ['email' => $old['email'], 'token' => $old['token']])
        ->fillForm(['password' => 'a-brand-new-password-1', 'passwordConfirmation' => 'a-brand-new-password-1'])
        ->call('resetPassword')
        ->assertNotified(__('passwords.token'));

    expect($admin->fresh()->status)->toBe(AdminStatus::PENDING);
});
