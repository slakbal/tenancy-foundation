<?php

use Illuminate\Support\Facades\Schema;

test('the sortable timestamp columns of the user and contact request tables are indexed', function (string $table, string $column) {
    expect(Schema::hasIndex($table, [$column]))->toBeTrue();
})->with([
    ['users', 'last_login_at'],
    ['users', 'created_at'],
    ['admins', 'last_login_at'],
    ['admins', 'created_at'],
    ['contact_requests', 'created_at'],
]);
