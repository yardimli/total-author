<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_messages', fn (Blueprint $table) => $table->string('status', 20)->nullable());
        // Recover known failures from earlier versions without guessing from user-authored text.
        $key = 'The request could not be completed. No manuscript or codex changes were applied. Check the error and usage status before trying again.';
        DB::table('chat_messages')->where('role', 'assistant')->whereIn('content', [__($key, [], 'en'), __($key, [], 'tr')])->orderBy('id')->each(function ($reply) {
            $id = DB::table('chat_messages')->where('book_id', $reply->book_id)->where('role', 'user')->where('id', '<', $reply->id)->max('id');
            if ($id) DB::table('chat_messages')->where('id', $id)->update(['status' => 'failed']);
        });
    }
    public function down(): void { Schema::table('chat_messages', fn (Blueprint $table) => $table->dropColumn('status')); }
};
