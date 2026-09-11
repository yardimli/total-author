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
            $table->timestamp('favorites_initialized_at')->nullable();
        });
        DB::table('users')->whereNotNull('favorite_models')->where('favorite_models', '!=', '[]')->update(['favorites_initialized_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('favorites_initialized_at');
        });
    }
};
