<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shopping_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedInteger('estimated_yen')->default(0);
            $table->unsignedInteger('actual_yen')->nullable();
            $table->boolean('is_bought')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['trip_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopping_items');
    }
};
