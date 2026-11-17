<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Models\Admin;
use Spatie\Permission\Models\Role;

const INSTALL_COMMAND = 'tenancy-foundation:install';

beforeEach(function () {
    $this->publishedConfig = config_path('tenancy-foundation.php');
    $this->originalConfig = File::exists($this->publishedConfig) ? File::get($this->publishedConfig) : null;
});

afterEach(function () {
    $this->originalConfig === null
        ? File::delete($this->publishedConfig)
        : File::put($this->publishedConfig, $this->originalConfig);
});

it('keeps an existing config unless overwriting is confirmed', function (string $answer, bool $overwritten) {
    File::put($this->publishedConfig, '<?php return [];');

    $this->artisan(INSTALL_COMMAND)
        ->expectsConfirmation('config/tenancy-foundation.php already exists. Overwrite it with the package defaults?', $answer)
        ->expectsConfirmation('Run the migrations now?', 'no')
        ->expectsConfirmation('Seed the admin and tenant roles now?', 'no')
        ->expectsConfirmation('Invite the first admin now?', 'no')
        ->assertSuccessful();

    expect(File::get($this->publishedConfig) !== '<?php return [];')->toBe($overwritten);
})->with([
    'declined' => ['no', false],
    'confirmed' => ['yes', true],
]);

it('seeds the roles and invites the first admin when asked', function () {
    Notification::fake();
    File::delete($this->publishedConfig);

    $this->artisan(INSTALL_COMMAND)
        ->expectsConfirmation('Run the migrations now?', 'no')
        ->expectsConfirmation('Seed the admin and tenant roles now?', 'yes')
        ->expectsConfirmation('Invite the first admin now?', 'yes')
        ->expectsQuestion('Email address of the new admin', 'first@example.com')
        ->expectsQuestion('Name', 'First Admin')
        ->expectsQuestion('Role', AdminRoles::SUPER_ADMIN->value)
        ->assertSuccessful();

    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

    expect(Role::where('name', TenantRoles::CUSTOMER->value)->where('guard_name', AuthGuard::TENANT->value)->exists())->toBeTrue()
        ->and(Admin::query()->where('email', 'first@example.com')->firstOrFail()->hasRole(AdminRoles::SUPER_ADMIN))->toBeTrue();
});

it('asks nothing and lists the remaining steps without interaction', function () {
    File::delete($this->publishedConfig);

    $this->artisan(INSTALL_COMMAND, ['--no-interaction' => true])
        ->expectsOutputToContain('Published config/tenancy-foundation.php')
        ->expectsOutputToContain('tenancy-foundation:create-admin')
        ->assertSuccessful();

    expect(Admin::query()->count())->toBe(0);
});
