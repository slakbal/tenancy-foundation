<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;

test('an ability with no mapped permission at all resolves false rather than throwing (e.g. TenantPolicy has no restore)', function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $tenant = Tenant::factory()->create();
    setPermissionsTeamId($tenant->getKey());
    $owner = User::factory()->create();
    $tenant->members()->attach($owner, ['is_owner' => true, 'is_default_tenant' => true]);

    expect($owner->can('restore', $tenant))->toBeFalse();
});
