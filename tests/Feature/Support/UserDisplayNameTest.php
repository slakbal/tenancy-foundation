<?php

use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Support\UserDisplayName;

test('it builds the name from the configured name attributes', function () {
    config(['tenancy-foundation.user_name_attributes' => ['first_name', 'last_name']]);

    $user = (new User)->forceFill(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test']);

    expect(UserDisplayName::for($user))->toBe('Ada Lovelace');
});

test('it falls back to the email when no name attribute is set', function () {
    $user = (new User)->forceFill(['name' => null, 'email' => 'ada@example.test']);

    expect(UserDisplayName::for($user))->toBe('ada@example.test');
});
