<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Slakbal\TenancyFoundation\Enums\DomainRuleType;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_rules', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained((new (config('tenancy-foundation.models.tenant')))->getTable())->cascadeOnDelete();
            $table->string('value');
            $table->string('type')->default(DomainRuleType::BLOCK)->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'value']);
        });

        Schema::create('disposable_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
        });
    }
};
