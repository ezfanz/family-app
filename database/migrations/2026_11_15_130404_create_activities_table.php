<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Activities use client-generated UUIDs so offline-queued creates are idempotent.
     * trip_id is denormalised from the day so authorisation and the changes feed are a
     * single indexed lookup, and a stop moved to another day stays in the same scope.
     */
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('day_id')->constrained()->cascadeOnDelete();
            $table->time('start_time');
            $table->string('name', 160);
            $table->string('transport', 200)->default('');
            $table->string('meal', 20)->default('');
            $table->string('ticket', 40)->default('');
            $table->text('notes')->nullable();
            $table->jsonb('places')->nullable();
            $table->boolean('is_halal')->default(false);
            $table->string('status', 10)->default('pending');
            $table->text('user_note')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['day_id', 'start_time']);
            $table->index(['trip_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
