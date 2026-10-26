<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function recreateDefaultUsersTable(): void
{
    Schema::disableForeignKeyConstraints();
    Schema::dropIfExists('users');
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });
    Schema::enableForeignKeyConstraints();
}

function runUsersTenancyMigration(): void
{
    (require __DIR__.'/../../../database/migrations/0001_02_01_000000_add_tenancy_columns_to_users_table.php')->up();
}

test('the package migration adds the tenancy columns to the default laravel users table', function () {
    expect(Schema::hasColumns('users', ['ulid', 'name', 'last_login_at', 'last_login_ip', 'deleted_at']))->toBeTrue();
});

test('existing users are backfilled with unique ulids', function () {
    recreateDefaultUsersTable();
    foreach (['a', 'b', 'c'] as $letter) {
        DB::table('users')->insert(['name' => $letter, 'email' => "{$letter}@example.com", 'password' => 'x']);
    }

    runUsersTenancyMigration();

    $ulids = DB::table('users')->pluck('ulid');

    expect($ulids)->toHaveCount(3)
        ->and($ulids->filter()->unique())->toHaveCount(3)
        ->and($ulids->every(fn (string $ulid): bool => strlen($ulid) === 26))->toBeTrue();
});

test('running the migration again on an already adapted table is a no-op', function () {
    $before = Schema::getColumnListing('users');

    runUsersTenancyMigration();

    expect(Schema::getColumnListing('users'))->toBe($before);
});

test('configured name attributes missing from the table are added as nullable columns', function () {
    config(['tenancy-foundation.user_name_attributes' => ['first_name', 'last_name']]);
    recreateDefaultUsersTable();

    runUsersTenancyMigration();

    expect(Schema::hasColumns('users', ['first_name', 'last_name', 'name']))->toBeTrue();
    DB::table('users')->insert(['name' => 'x', 'email' => 'x@example.com', 'password' => 'x', 'ulid' => '01JABCDEFGHJKMNPQRSTVWXYZ0']);
    expect(DB::table('users')->count())->toBe(1);
});
