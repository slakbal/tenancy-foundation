<?php

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Listeners\Auth\UpdateLastLogin;
use Slakbal\TenancyFoundation\Models\User;

test('logging in records the login time and ip without touching updated_at or firing model events', function () {
    $user = User::factory()->create(['updated_at' => now()->subDays(10)]);
    $originalUpdatedAt = $user->updated_at;

    Event::fake(['eloquent.updated: '.User::class]);

    (new UpdateLastLogin)->handle(new Login(AuthGuard::TENANT->value, $user, false));

    $user->refresh();

    expect($user->last_login_at)->not->toBeNull()
        ->and($user->last_login_ip)->not->toBeNull()
        ->and($user->updated_at->equalTo($originalUpdatedAt))->toBeTrue();

    Event::assertNotDispatched('eloquent.updated: '.User::class);
});
