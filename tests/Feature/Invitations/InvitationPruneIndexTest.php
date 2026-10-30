<?php

use Illuminate\Support\Facades\Schema;

test('the invitations table has an index matching the prune query', function () {
    expect(Schema::hasIndex('invitations', ['accepted_at', 'expires_at']))->toBeTrue();
});
