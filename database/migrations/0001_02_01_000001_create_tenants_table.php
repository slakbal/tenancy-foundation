<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Slakbal\TenancyFoundation\Enums\MemberStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->string('subdomain')->unique();
            $table->string('billing_email')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('scheduled_for_deletion_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('tenant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(config('tenancy-foundation.models.user'))->constrained()->cascadeOnDelete();
            $table->foreignIdFor(config('tenancy-foundation.models.tenant'))->constrained()->cascadeOnDelete();
            $table->boolean('is_owner')->default(false);
            $table->boolean('is_default_tenant')->default(false);
            $table->string('status')->default(MemberStatus::ACTIVE)->index();
            $table->timestamp('accepted_invite_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tenant_id']);
            $table->index(['tenant_id', 'is_owner']);
        });
    }
};
