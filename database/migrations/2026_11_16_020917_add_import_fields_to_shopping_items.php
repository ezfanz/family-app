<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Extra item fields from the Excel shopping list template.
     */
    public function up(): void
    {
        Schema::table('shopping_items', function (Blueprint $table) {
            $table->string('store', 60)->nullable()->after('name');
            $table->string('details', 160)->nullable()->after('store');
            $table->string('link', 500)->nullable()->after('for_whom');
            $table->text('notes')->nullable()->after('link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopping_items', function (Blueprint $table) {
            $table->dropColumn(['store', 'details', 'link', 'notes']);
        });
    }
};
