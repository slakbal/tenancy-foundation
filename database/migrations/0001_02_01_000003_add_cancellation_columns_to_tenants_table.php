<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }

            if (! Schema::hasColumn('tenants', 'scheduled_for_deletion_at')) {
                $table->timestamp('scheduled_for_deletion_at')->nullable()->index();
            }
        });
    }
};
