<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages\ListUsers as AdminListUsers;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\ListUsers as TenantListUsers;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Permission;

function listQueryCount(string $page): int
{
    $count = 0;

    DB::listen(function () use (&$count): void {
        $count++;
    });

    Livewire::test($page)->loadTable()->assertSuccessful();

    return $count;
}

function assertListQueryCountIsConstant(string $page, Closure $addRows): void
{
    $addRows(1);
    listQueryCount($page);

    $withFewRows = listQueryCount($page);

    $addRows(9);
    $withManyRows = listQueryCount($page);

    expect($withManyRows)->toBe($withFewRows);
}

test('the admin users list runs the same number of queries regardless of row count', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(1);

    $admin = Admin::factory()->create();
    Permission::findOrCreate(AdminPermissions::USER_VIEW_ANY->value, AuthGuard::ADMIN->value);
    $admin->givePermissionTo(AdminPermissions::USER_VIEW_ANY);
    $this->actingAs($admin, AuthGuard::ADMIN->value);

    assertListQueryCountIsConstant(AdminListUsers::class, function (int $rows): void {
        User::factory()->count($rows)->create()->each(
            fn (User $user) => Tenant::factory()->create()->members()->attach($user, ['is_owner' => false, 'is_default_tenant' => true])
        );
    });
});

test('the tenant users list runs the same number of queries regardless of row count', function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->create();
    $tenant->members()->attach($viewer, ['is_owner' => false, 'is_default_tenant' => true]);

    setPermissionsTeamId($tenant->getKey());
    Permission::findOrCreate(TenantPermissions::USER_VIEW_ANY->value, AuthGuard::TENANT->value);
    $viewer->givePermissionTo(TenantPermissions::USER_VIEW_ANY);

    $this->actingAs($viewer, AuthGuard::TENANT->value);
    Filament::setTenant($tenant);

    assertListQueryCountIsConstant(TenantListUsers::class, function (int $rows) use ($tenant): void {
        $tenant->members()->syncWithoutDetaching(
            User::factory()->count($rows)->create()->mapWithKeys(
                fn (User $user): array => [$user->getKey() => ['is_owner' => false, 'is_default_tenant' => true]]
            )->all(),
        );
    });
});
