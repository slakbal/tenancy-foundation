<?php

use Illuminate\Support\Facades\DB;
use Slakbal\TenancyFoundation\Models\Invitation;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Notifications\InvitationCreated;

test('the invitation mail names the tenant and loads it with a single query', function () {
    $tenant = Tenant::factory()->create(['name' => 'Acme Corp']);
    $invitation = Invitation::factory()->create(['tenant_id' => $tenant->id]);
    $invitation = Invitation::query()->findOrFail($invitation->id);

    $tenantQueries = 0;
    DB::listen(function ($query) use (&$tenantQueries): void {
        if (str_starts_with($query->sql, 'select * from "tenants"')) {
            $tenantQueries++;
        }
    });

    $mail = (new InvitationCreated($invitation))->toMail($invitation);

    expect($mail->subject)->toContain('Acme Corp')
        ->and($mail->introLines[0])->toContain('Acme Corp')
        ->and($tenantQueries)->toBe(1);
});
