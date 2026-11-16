<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tax refund tracking (Japan's refund method from 1 Nov 2026). Stores and
     * customs keep the official records; this only tracks what each person
     * expects back. Eligibility and totals are computed, never stored.
     */
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('store_name', 80)->nullable();
            $table->string('branch', 80)->nullable();
            $table->timestamp('purchased_at');
            $table->unsignedInteger('pre_tax_yen')->nullable();
            $table->unsignedTinyInteger('tax_rate')->default(10);
            $table->boolean('is_estimated')->default(false);
            $table->string('stage', 20)->default('checked');
            $table->timestamp('customs_confirmed_at')->nullable();
            $table->unsignedInteger('refund_received_yen')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['trip_id', 'user_id']);
            $table->index(['trip_id', 'updated_at']);
        });

        Schema::create('receipt_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('receipt_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('part')->default(1);
            $table->string('path');
            $table->string('mime', 40);
            $table->unsignedInteger('bytes');
            $table->timestamps();

            $table->unique(['receipt_id', 'part']);
        });

        Schema::create('receipt_shopping_item', function (Blueprint $table) {
            $table->foreignUuid('receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('shopping_item_id')->constrained()->cascadeOnDelete();

            $table->primary(['receipt_id', 'shopping_item_id']);
        });

        Schema::table('trip_user', function (Blueprint $table) {
            $table->json('airport_checklist')->nullable()->after('budget_rm');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->string('system_key', 40)->nullable()->after('day_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('system_key');
        });

        Schema::table('trip_user', function (Blueprint $table) {
            $table->dropColumn('airport_checklist');
        });

        Schema::dropIfExists('receipt_shopping_item');
        Schema::dropIfExists('receipt_photos');
        Schema::dropIfExists('receipts');
    }
};
