<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_proposals', function (Blueprint $table) {
            $table->foreignId('chat_message_id')->nullable()->constrained('chat_messages')->nullOnDelete();
        });
        // Earlier proposals and their replies were created in the same transaction.
        // Backfill only unambiguous matches; never attach a diff to an unrelated reply.
        DB::table('ai_proposals')->orderBy('id')->chunkById(100, function ($proposals) {
            foreach ($proposals as $proposal) {
                $messages = DB::table('chat_messages')->where('book_id', $proposal->book_id)
                    ->where('role', 'assistant')->where('created_at', $proposal->created_at)->pluck('id');
                if ($messages->count() === 1) {
                    DB::table('ai_proposals')->where('id', $proposal->id)->update(['chat_message_id' => $messages->first()]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_proposals', fn (Blueprint $table) => $table->dropConstrainedForeignId('chat_message_id'));
    }
};
