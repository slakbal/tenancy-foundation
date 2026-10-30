<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Tenant\AddTenantOwner;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages\ViewTenant;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\RelationManagers\UsersRelationManager;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages\ViewUser;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\RelationManagers\TenantsRelationManager;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\ListUsers as TenantListUsers;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;

function tableQueries(string $component, array $parameters): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test($component, $parameters)->loadTable()->assertSuccessful();

    $queries = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();

    return $queries;
}

function assertQueryCountIsConstant(string $component, Closure $parameters, Closure $addRows): void
{
    $addRows(1);
    tableQueries($component, $parameters());

    $few = count(tableQueries($component, $parameters()));

    $addRows(8);
    $many = count(tableQueries($component, $parameters()));

    expect($many)->toBe($few);
}

test('the tenant users tab keeps its query count with the owner, role and removal actions', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);
    $this->actingAs(adminWithAccess(AdminPermissions::TENANT_VIEW_ANY, AdminPermissions::TENANT_VIEW, AdminPermissions::USER_VIEW_ANY, AdminPermissions::USER_UPDATE), AuthGuard::ADMIN->value);

    $tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], User::factory()->create());

    assertQueryCountIsConstant(
        UsersRelationManager::class,
        fn (): array => ['ownerRecord' => $tenant->fresh(), 'pageClass' => ViewTenant::class],
        function (int $rows) use ($tenant): void {
            User::factory()->count($rows)->create()->each(function (User $user) use ($tenant): void {
                $tenant->members()->attach($user, ['is_owner' => false, 'is_default_tenant' => true]);
                app(AddTenantOwner::class)->handle($tenant, $user);
            });
        },
    );
});

test('the user tenants tab keeps its query count with the owner, role and removal actions', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);
    $this->actingAs(adminWithAccess(AdminPermissions::TENANT_VIEW_ANY, AdminPermissions::TENANT_VIEW, AdminPermissions::USER_VIEW_ANY, AdminPermissions::USER_VIEW, AdminPermissions::USER_UPDATE), AuthGuard::ADMIN->value);

    $user = User::factory()->create();

    assertQueryCountIsConstant(
        TenantsRelationManager::class,
        fn (): array => ['ownerRecord' => $user->fresh(), 'pageClass' => ViewUser::class],
        function (int $rows) use ($user): void {
            Tenant::factory()->count($rows)->create()->each(function (Tenant $tenant) use ($user): void {
                $tenant->members()->attach(User::factory()->create(), ['is_owner' => true, 'is_default_tenant' => true]);
                $tenant->members()->attach($user, ['is_owner' => true, 'is_default_tenant' => false]);
            });
        },
    );
});

test('the tenant panel users list keeps its query count with the owner actions', function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $owner = User::factory()->create();
    $tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $owner);
    setPermissionsTeamId($tenant->getKey());
    $this->actingAs($owner, AuthGuard::TENANT->value);
    Filament::setTenant($tenant);

    assertQueryCountIsConstant(
        TenantListUsers::class,
        fn (): array => [],
        function (int $rows) use ($tenant): void {
            User::factory()->count($rows)->create()->each(function (User $user) use ($tenant): void {
                $tenant->members()->syncWithoutDetaching([$user->getKey() => ['is_owner' => false, 'is_default_tenant' => true]]);
                app(AddTenantOwner::class)->handle($tenant, $user);
            });
        },
    );
});
