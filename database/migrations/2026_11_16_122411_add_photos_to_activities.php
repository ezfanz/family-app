<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A photo per stop: found automatically on Wikimedia Commons, or uploaded
     * by an editor. "none" means someone removed it, so it is not looked up again.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('photo_source', 20)->nullable()->after('places');
            $table->string('photo_url', 1000)->nullable()->after('photo_source');
            $table->string('photo_thumb_url', 1000)->nullable()->after('photo_url');
            $table->string('photo_credit', 300)->nullable()->after('photo_thumb_url');
            $table->string('photo_link', 1000)->nullable()->after('photo_credit');
            $table->string('photo_path')->nullable()->after('photo_link');
            $table->timestamp('photo_checked_at')->nullable()->after('photo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['photo_source', 'photo_url', 'photo_thumb_url', 'photo_credit', 'photo_link', 'photo_path', 'photo_checked_at']);
        });
    }
};
