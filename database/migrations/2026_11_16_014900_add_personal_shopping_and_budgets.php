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
     * Shopping becomes personal: every item belongs to one member and every
     * member has their own budget. Existing items move to the trip owner and
     * the old trip-wide "banked in" amount becomes the owner's budget.
     */
    public function up(): void
    {
        Schema::table('shopping_items', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('trip_id')->constrained()->nullOnDelete();
            $table->index(['trip_id', 'user_id']);
        });

        Schema::table('trip_user', function (Blueprint $table) {
            $table->decimal('budget_rm', 12, 2)->default(0)->after('role');
        });

        $owners = DB::table('trip_user')->where('role', 'owner')->orderBy('id')->get()->unique('trip_id');
        foreach ($owners as $owner) {
            DB::table('shopping_items')->where('trip_id', $owner->trip_id)->whereNull('user_id')->update(['user_id' => $owner->user_id]);

            $deposit = DB::table('trips')->where('id', $owner->trip_id)->value('deposit_rm');
            DB::table('trip_user')->where('id', $owner->id)->update(['budget_rm' => $deposit ?? 0]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_user', function (Blueprint $table) {
            $table->dropColumn('budget_rm');
        });

        Schema::table('shopping_items', function (Blueprint $table) {
            $table->dropIndex(['trip_id', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
