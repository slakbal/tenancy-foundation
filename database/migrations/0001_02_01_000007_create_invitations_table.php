<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained((new (config('tenancy-foundation.models.tenant')))->getTable())->cascadeOnDelete();
            $table->unsignedBigInteger('role_id')->nullable()->index();
            $table->foreignId('invited_by')->nullable()->constrained((new (config('tenancy-foundation.models.user')))->getTable())->nullOnDelete();
            $table->string('email')->index();
            $table->string('token')->unique();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'accepted_at']);
            $table->index(['accepted_at', 'expires_at']);
        });
    }
};
