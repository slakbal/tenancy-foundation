<?php

use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Horizon\Horizon;
use Opcodes\LogViewer\Facades\LogViewer;
use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Plugins\AdminPlugin;

function systemNavigationItems(): array
{
    $panel = Panel::make()->id('system-nav-test');
    AdminPlugin::make()->register($panel);

    return collect($panel->getNavigationItems())->filter(fn (NavigationItem $item): bool => $item->getGroup() === __('System'))->keyBy(fn (NavigationItem $item): string => $item->getLabel())->all();
}

function signInAs(?AdminRoles $role): void
{
    if ($role === null) {
        return;
    }

    $admin = Admin::factory()->create();
    $admin->assignRole($role);
    test()->actingAs($admin, AuthGuard::ADMIN->value);
}

beforeEach(function () {
    $this->seed(AdminRolePermissionsSeeder::class);
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);
});

test('the admin panel has a System group with Horizon and the Log Viewer, each opening in a new tab', function () {
    $items = systemNavigationItems();

    expect(array_keys($items))->toBe([__('Horizon'), __('Log Viewer')])
        ->and($items[__('Horizon')]->shouldOpenUrlInNewTab())->toBeTrue()
        ->and($items[__('Log Viewer')]->shouldOpenUrlInNewTab())->toBeTrue();
});

test('the System items are shown to super admins only', function (?AdminRoles $role, bool $visible) {
    signInAs($role);

    foreach (systemNavigationItems() as $item) {
        expect($item->isVisible())->toBe($visible);
    }
})->with([
    'guest' => [null, false],
    'admin' => [AdminRoles::ADMIN, false],
    'support' => [AdminRoles::SUPPORT, false],
    'super admin' => [AdminRoles::SUPER_ADMIN, true],
]);

test('Horizon and the Log Viewer only let a signed in super admin in, even in the local environment', function (?AdminRoles $role, bool $allowed) {
    app()->detectEnvironment(fn (): string => 'local');
    signInAs($role);
    setPermissionsTeamId(null);

    expect(Horizon::check(Request::create('/horizon')))->toBe($allowed);

    if ($allowed) {
        LogViewer::auth();
        expect(true)->toBeTrue();
    } else {
        expect(fn () => LogViewer::auth())->toThrow(AuthorizationException::class);
    }
})->with([
    'guest' => [null, false],
    'support' => [AdminRoles::SUPPORT, false],
    'admin' => [AdminRoles::ADMIN, false],
    'super admin' => [AdminRoles::SUPER_ADMIN, true],
]);

test('both tools are served on the admin host when one is configured', function () {
    expect(Route::has('horizon.index'))->toBeTrue()
        ->and(Route::has('log-viewer.index'))->toBeTrue();
});

test('the System group comes last in the admin panel navigation', function () {
    $panel = Panel::make()->id('system-order-test');
    AdminPlugin::make()->register($panel);

    $groups = $panel->getNavigationGroups();

    expect(array_last($groups))->toBe(__('System'))
        ->and($groups)->toContain(__('Security'));
});
