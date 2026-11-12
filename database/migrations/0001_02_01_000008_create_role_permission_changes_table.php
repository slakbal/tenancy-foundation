<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permission_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->index();
            $table->string('role_name');
            $table->string('guard_name');
            $table->json('added');
            $table->json('removed');
            $table->nullableMorphs('actor');
            $table->timestamp('created_at')->nullable();
        });
    }
};
