<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_calls', function (Blueprint $table) {
            $table->longText('request_payload')->nullable();
            $table->longText('response_body')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('error')->nullable();
        });
        Schema::table('chat_messages', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        Schema::table('ai_calls', fn (Blueprint $table) => $table->dropColumn(['request_payload', 'response_body', 'response_status', 'error']));
        Schema::table('chat_messages', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
