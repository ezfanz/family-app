<?php

use App\Models\Trip;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Trips created before the tax refund module get the "tax refund at
     * customs" stop on their last day. New trips get it when they start.
     */
    public function up(): void
    {
        Trip::query()->each(fn (Trip $trip) => $trip->ensureTaxRefundStop());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('activities')->where('system_key', 'tax_refund')->delete();
    }
};
