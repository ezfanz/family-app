<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Follows the Tabi design: private per-person stop notes, a recipient on
     * shopping items, richer ideas that can be planned onto a day and deleted,
     * and blank days for newly started trips.
     */
    public function up(): void
    {
        Schema::create('activity_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('note');
            $table->timestamps();

            $table->unique(['activity_id', 'user_id']);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('user_note');
        });

        Schema::table('shopping_items', function (Blueprint $table) {
            $table->string('for_whom', 80)->nullable()->after('name');
        });

        Schema::table('ideas', function (Blueprint $table) {
            $table->string('city', 32)->nullable()->after('name');
            $table->string('area', 80)->nullable()->after('city');
            $table->foreignId('planned_day_id')->nullable()->after('is_visited')->constrained('days')->nullOnDelete();
            $table->softDeletes();
        });

        Schema::table('days', function (Blueprint $table) {
            $table->string('city', 32)->nullable()->change();
            $table->string('title', 160)->default('')->change();
        });
    }

    /**
     * Reverse the migrations. Private notes are lost on rollback.
     */
    public function down(): void
    {
        Schema::table('days', function (Blueprint $table) {
            $table->string('city', 32)->nullable(false)->change();
            $table->string('title', 160)->default(null)->change();
        });

        Schema::table('ideas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('planned_day_id');
            $table->dropColumn(['city', 'area']);
            $table->dropSoftDeletes();
        });

        Schema::table('shopping_items', function (Blueprint $table) {
            $table->dropColumn('for_whom');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->text('user_note')->nullable();
        });

        Schema::dropIfExists('activity_notes');
    }
};
