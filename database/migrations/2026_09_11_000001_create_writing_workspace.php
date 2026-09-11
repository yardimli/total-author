<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('openrouter_key')->nullable();
            $table->string('selected_model')->nullable();
            $table->json('favorite_models')->nullable();
            $table->string('theme')->default('paper');
            $table->decimal('demo_spent', 16, 8)->default(0);
            $table->decimal('demo_reserved', 16, 8)->default(0);
        });
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->json('document');
            $table->longText('manuscript')->nullable();
            $table->json('metadata')->nullable();
            $table->json('codex_types');
            $table->unsignedInteger('revision')->default(1);
            $table->boolean('archived')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('codex_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->longText('content')->nullable();
            $table->json('aliases');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
        Schema::create('revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->json('snapshot');
            $table->timestamps();
        });
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->longText('content');
            $table->uuid('request_id')->nullable()->unique();
            $table->timestamps();
        });
        Schema::create('ai_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('base_revision');
            $table->json('changes');
            $table->json('decisions')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        Schema::create('ai_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('model');
            $table->string('stage');
            $table->string('funding');
            $table->string('status')->default('reserved');
            $table->string('provider_id')->nullable();
            $table->decimal('reserved', 16, 8)->default(0);
            $table->decimal('cost', 16, 8)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['ai_calls', 'ai_proposals', 'chat_messages', 'revisions', 'codex_entries', 'books'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['openrouter_key', 'selected_model', 'favorite_models', 'theme', 'demo_spent', 'demo_reserved']));
    }
};
