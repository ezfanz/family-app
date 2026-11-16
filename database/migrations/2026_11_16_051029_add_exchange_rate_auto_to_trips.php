<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * When on (the default), shopping uses today's Bank Negara Malaysia rate;
     * when off, the trip's own exchange_rate_rm_per_100_yen.
     */
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->boolean('exchange_rate_auto')->default(true)->after('exchange_rate_rm_per_100_yen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('exchange_rate_auto');
        });
    }
};
