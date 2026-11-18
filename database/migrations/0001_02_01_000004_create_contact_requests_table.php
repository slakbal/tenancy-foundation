<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignIdFor(config('tenancy-foundation.models.tenant'))->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(config('tenancy-foundation.models.user'))->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->text('message');
            $table->string('source')->default('public')->index();
            $table->string('status')->default('new');
            $table->text('response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('responded_by')->nullable();
            $table->timestamp('closed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }
};
