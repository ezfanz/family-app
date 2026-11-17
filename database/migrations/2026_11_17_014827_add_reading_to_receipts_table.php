<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What Claude read off the receipt photo: an English summary and the
     * items, translated. Kept so the summary shows whenever the receipt opens.
     */
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->json('reading')->nullable()->after('refunded_at');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('reading');
        });
    }
};
