<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('favorites_only')->default(true);
            $table->timestamp('model_selected_at')->nullable();
        });
        DB::table('users')->whereNotNull('selected_model')->where('selected_model', '!=', '')->update(['model_selected_at' => now(), 'favorites_only' => false]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['favorites_only', 'model_selected_at']);
        });
    }
};
