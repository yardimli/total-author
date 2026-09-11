<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_calls', function (Blueprint $table) {
            $table->unsignedBigInteger('prompt_tokens')->nullable();
            $table->unsignedBigInteger('completion_tokens')->nullable();
            $table->unsignedBigInteger('total_tokens')->nullable();
        });
        DB::table('ai_calls')->whereNotNull('response_body')->orderBy('id')->chunkById(100, function ($calls) {
            foreach ($calls as $call) {
                $usage = json_decode($call->response_body, true)['usage'] ?? [];
                $values = [];
                foreach (['prompt_tokens', 'completion_tokens', 'total_tokens'] as $key) {
                    if (isset($usage[$key]) && is_numeric($usage[$key]) && $usage[$key] >= 0) {
                        $values[$key] = (int) $usage[$key];
                    }
                }
                if ($values) {
                    DB::table('ai_calls')->where('id', $call->id)->update($values);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_calls', fn (Blueprint $table) => $table->dropColumn(['prompt_tokens', 'completion_tokens', 'total_tokens']));
    }
};
