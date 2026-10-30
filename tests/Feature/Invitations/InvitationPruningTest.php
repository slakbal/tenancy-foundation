<?php

use Slakbal\TenancyFoundation\Models\Invitation;
use Slakbal\TenancyFoundation\Models\Tenant;

test('prunable() uses the expired scope and real pruning removes only expired invitations', function () {
    config(['tenancy-foundation.invitations.retention_days' => 30]);

    $tenant = Tenant::factory()->create();

    $stillPending = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'accepted_at' => null,
        'expires_at' => now()->addDays(3),
    ]);

    $expiredPending = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'accepted_at' => null,
        'expires_at' => now()->subDay(),
    ]);

    $recentlyAccepted = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'accepted_at' => now()->subDay(),
        'expires_at' => now()->subDays(2),
    ]);

    $longAccepted = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'accepted_at' => now()->subDays(40),
        'expires_at' => now()->subDays(47),
    ]);

    (new Invitation)->pruneAll();

    expect(Invitation::find($stillPending->id))->not->toBeNull();
    expect(Invitation::find($expiredPending->id))->toBeNull();
    expect(Invitation::find($recentlyAccepted->id))->not->toBeNull();
    expect(Invitation::find($longAccepted->id))->toBeNull();
});

test('the expired scope keeps earlier constraints when chained', function () {
    config(['tenancy-foundation.invitations.retention_days' => 30]);

    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();

    $mine = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'accepted_at' => null,
        'expires_at' => now()->subDay(),
    ]);

    $theirs = Invitation::factory()->create([
        'tenant_id' => $otherTenant->id,
        'accepted_at' => now()->subDays(40),
        'expires_at' => now()->subDays(47),
    ]);

    $ids = Invitation::query()->where('tenant_id', $tenant->id)->expired()->pluck('id')->all();

    expect($ids)->toBe([$mine->id]);
    expect($ids)->not->toContain($theirs->id);
});
