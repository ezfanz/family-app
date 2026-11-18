<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Comments on a stop: a thread people post to (and @mention each other in).
     * Each person's existing note becomes their first comment; the old notes
     * table is left in place untouched.
     */
    public function up(): void
    {
        Schema::create('stop_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->json('mention_ids')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['activity_id', 'created_at']);
            $table->index('updated_at');
        });

        DB::table('activity_notes')->where('note', '!=', '')->orderBy('id')->each(function (object $note) {
            DB::table('stop_comments')->insert([
                'id' => (string) Str::uuid7(),
                'activity_id' => $note->activity_id,
                'user_id' => $note->user_id,
                'body' => $note->note,
                'mention_ids' => null,
                'created_at' => $note->updated_at ?? $note->created_at,
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stop_comments');
    }
};
