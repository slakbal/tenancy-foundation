<?php

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
use Slakbal\TenancyFoundation\Models\Admin;
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
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

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
            app(CreateTenant::class)->handle(['name' => "Extra {$created}", ...tenantAddress("extra{$created}")], User::factory()->create());
        }

        setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);
    });
});

test('the users lists load each row\'s tenant memberships with one query', function (string $panel, string $page) {
    Filament::setCurrentPanel(Filament::getPanel($panel));
    Filament::bootCurrentPanel();

    $tenant = Tenant::factory()->create();
    $tenant->members()->attach(User::factory()->count(3)->create()->pluck('id'), ['is_owner' => false, 'is_default_tenant' => true]);
    $tenant->members()->attach(User::factory()->create(), ['is_owner' => true, 'is_default_tenant' => true]);

    if ($panel === 'admin') {
        setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);
        $this->actingAs(adminWithAccess(AdminPermissions::USER_VIEW_ANY), AuthGuard::ADMIN->value);
    } else {
        setPermissionsTeamId($tenant->getKey());
        $this->actingAs(tenantMemberWithPermission($tenant, TenantPermissions::USER_VIEW_ANY), AuthGuard::TENANT->value);
        Filament::setTenant($tenant);
    }

    $component = Livewire::test($page);
    $membershipLoads = 0;

    DB::listen(function ($query) use (&$membershipLoads): void {
        if (preg_match('/inner join "tenant_user" .* "tenant_user"\."user_id" in \(\d+(, \d+)+\)/', $query->sql)) {
            $membershipLoads++;
        }
    });

    $component->loadTable()
        ->assertCanSeeTableRecords(User::query()->whereHas('tenants')->get())
        ->assertTableColumnStateSet('owned_tenants', [$tenant->name], User::query()->whereRelation('tenants', 'tenant_user.is_owner', true)->firstOrFail());

    expect($membershipLoads)->toBe(1);
})->with([
    'admin' => ['admin', AdminListUsers::class],
    'tenant' => ['app', TenantListUsers::class],
]);
