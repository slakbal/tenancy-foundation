<?php

use Slakbal\TenancyFoundation\Models\Admin;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages\ListTenants;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages\ListUsers as AdminListUsers;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\ListUsers as TenantListUsers;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;

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
    listQueryCount($page); // warm-up: first render fills one-off caches

    $withFewRows = listQueryCount($page);

    $addRows(9);
    $withManyRows = listQueryCount($page);

    expect($withManyRows)->toBe($withFewRows);
}

test('the admin users list runs the same number of queries regardless of row count', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(1);

    $this->actingAs(adminWithAccess(AdminPermissions::USER_VIEW_ANY), AuthGuard::ADMIN->value);

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
    setPermissionsTeamId($tenant->getKey());
    $viewer = tenantMemberWithPermission($tenant, TenantPermissions::USER_VIEW_ANY);

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

test('the admin tenants list runs the same number of queries regardless of row count', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

    $this->actingAs(adminWithAccess(AdminPermissions::TENANT_VIEW_ANY), AuthGuard::ADMIN->value);

    assertListQueryCountIsConstant(ListTenants::class, function (int $rows): void {
        static $created = 0;

        foreach (range(1, $rows) as $ignored) {
            $created++;
            app(CreateTenant::class)->handle(['name' => "Extra {$created}", 'subdomain' => "extra{$created}"], User::factory()->create());
        }

        setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);
    });
});
