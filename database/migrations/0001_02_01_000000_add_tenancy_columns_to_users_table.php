<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $addsUlid = ! Schema::hasColumn('users', 'ulid');

        Schema::table('users', function (Blueprint $table) use ($addsUlid) {
            if ($addsUlid) {
                $table->ulid('ulid')->nullable()->after('id');
            }

            $previous = 'ulid';

            foreach (config('tenancy-foundation.user_name_attributes') as $attribute) {
                if (! Schema::hasColumn('users', $attribute)) {
                    $table->string($attribute)->nullable()->index()->after($previous);
                }

                $previous = $attribute;
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->index()->after('password');
            }

            if (! Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip')->nullable()->after('last_login_at');
            }

            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }

            if (! Schema::hasIndex('users', ['created_at'])) {
                $table->index('created_at');
            }
        });

        if ($addsUlid) {
            DB::table('users')->whereNull('ulid')->orderBy('id')->each(
                fn (object $user) => DB::table('users')->where('id', $user->id)->update(['ulid' => Str::lower((string) Str::ulid())])
            );

            Schema::table('users', function (Blueprint $table) {
                $table->ulid('ulid')->nullable(false)->unique()->change();
            });
        }
    }
};
