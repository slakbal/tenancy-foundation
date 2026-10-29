<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Actions\CreateTenant;
use Slakbal\TenancyFoundation\Models\User;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);
    $this->other = app(CreateTenant::class)->handle(['name' => 'Other', 'subdomain' => 'other'], User::factory()->create());
});

it('reads ownership from the tenant_user flag for the given tenant', function () {
    $owner = $this->owner->fresh();

    expect($owner->isOwner($this->tenant))->toBeTrue()
        ->and($owner->isOwner($this->other))->toBeFalse();
});

it('defaults to the current Filament tenant and is false without one', function () {
    $owner = $this->owner->fresh();

    expect($owner->isOwner())->toBeFalse();

    Filament::setTenant($this->tenant, isQuiet: true);

    expect($owner->isOwner())->toBeTrue();
});

it('allows everything for the owner of the current tenant only', function () {
    $owner = $this->owner->fresh();
    Filament::setTenant($this->tenant, isQuiet: true);
    expect($owner->can('anything-at-all'))->toBeTrue();

    Filament::setTenant($this->other, isQuiet: true);
    expect($owner->fresh()->can('anything-at-all'))->toBeFalse();
});

it('does not grant a non owner member anything through the owner check', function () {
    $member = User::factory()->create();
    $this->tenant->members()->attach($member->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);
    Filament::setTenant($this->tenant, isQuiet: true);

    expect($member->fresh()->can('anything-at-all'))->toBeFalse();
});
