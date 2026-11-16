<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * For the Shopping overview: a category per item, and when Paid ¥ was first
     * entered (the day it counts towards). Items already paid get their last
     * update time, the closest thing we have.
     */
    public function up(): void
    {
        Schema::table('shopping_items', function (Blueprint $table) {
            $table->string('category', 30)->default('other')->after('for_whom');
            $table->timestamp('paid_at')->nullable()->after('actual_yen');
        });

        DB::table('shopping_items')->whereNotNull('actual_yen')->update(['paid_at' => DB::raw('updated_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopping_items', function (Blueprint $table) {
            $table->dropColumn(['category', 'paid_at']);
        });
    }
};
