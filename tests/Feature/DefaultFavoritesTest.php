<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use App\Services\Manuscript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DefaultFavoritesTest extends TestCase
{
    use RefreshDatabase;

    private function book(User $user): Book
    {
        return Book::create(['user_id' => $user->id, 'title' => 'Test', 'document' => Manuscript::fromText(''), 'codex_types' => ['People']]);
    }

    private function catalog(): array
    {
        return ['data' => [
            ['id' => 'openai/gpt-5.6-sol', 'architecture' => ['output_modalities' => ['text']]],
            ['id' => 'anthropic/claude-opus-5', 'architecture' => ['output_modalities' => ['text']]],
            ['id' => 'anthropic/claude-sonnet-5', 'name' => 'Batch model', 'architecture' => ['output_modalities' => ['text']]],
            ['id' => 'anthropic/claude-fable-5', 'architecture' => ['output_modalities' => ['text', 'image']]],
            ['id' => 'other/model', 'architecture' => ['output_modalities' => ['text']]],
        ], 'refreshed_at' => now()->toIso8601String()];
    }

    public function test_writing_page_seeds_only_matching_models_once_and_preserves_manual_empty_list(): void
    {
        $user = User::factory()->create();
        $book = $this->book($user);
        Cache::forever('openrouter.catalog', $this->catalog());
        Http::fake();
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->assertNull($user->fresh()->favorites_initialized_at);
        $this->get('/books/'.$book->id)->assertOk();
        $this->assertSame(['anthropic/claude-opus-5', 'openai/gpt-5.6-sol'], $user->fresh()->favorite_models);
        $this->patchJson('/account', ['favorite_models' => []])->assertOk();
        $this->get('/books/'.$book->id)->assertOk();
        $this->assertSame([], $user->fresh()->favorite_models);
        Http::assertNothingSent();
    }

    public function test_existing_favorites_and_other_members_books_are_untouched(): void
    {
        $user = User::factory()->create();
        $user->favorite_models = ['other/model'];
        Cache::forever('openrouter.catalog', $this->catalog());
        $user->save();
        $book = $this->book($user);
        Http::fake();
        $this->actingAs($user)->get('/books/'.$book->id)->assertOk();
        $this->assertSame(['other/model'], $user->fresh()->favorite_models);
        Http::assertNothingSent();
        $this->actingAs(User::factory()->create())->get('/books/'.$book->id)->assertNotFound();
    }

    public function test_login_refresh_is_used_once_and_missing_catalog_can_retry(): void
    {
        Cache::forget('openrouter.catalog');
        $user = User::factory()->create();
        $book = $this->book($user);
        Http::fake(['*/models' => Http::sequence()->push([], 503)->push($this->catalog())]);
        $this->actingAs($user)->withSession(['refresh_model_catalog' => true])->get('/books/'.$book->id)->assertOk();
        $this->assertNull($user->fresh()->favorites_initialized_at);
        $this->get('/books/'.$book->id)->assertOk();
        $this->assertNotNull($user->fresh()->favorites_initialized_at);
        $this->get('/api/models')->assertOk();
        Http::assertSentCount(2);
    }

    public function test_new_members_prefer_sol_over_cheaper_favorites_until_they_choose_a_model(): void
    {
        $user = User::factory()->create();
        $book = $this->book($user);
        $catalog = $this->catalog();
        $catalog['data'][0]['pricing'] = ['prompt' => '0.000003', 'completion' => '0.000020'];
        $catalog['data'][1]['pricing'] = ['prompt' => '0.000001', 'completion' => '0.000010'];
        Cache::forever('openrouter.catalog', $catalog);
        $this->actingAs($user)->get('/books/'.$book->id)->assertOk()->assertSee('id="favorites-only" type="checkbox" checked', false);
        $this->assertSame('openai/gpt-5.6-sol', $user->fresh()->selected_model);
        $this->assertNull($user->fresh()->model_selected_at);
        $this->patchJson('/account', ['selected_model' => 'anthropic/claude-opus-5', 'favorites_only' => false])->assertOk();
        $this->get('/books/'.$book->id)->assertOk();
        $this->assertSame('anthropic/claude-opus-5', $user->fresh()->selected_model);
        $this->assertNotNull($user->fresh()->model_selected_at);
        $this->assertFalse((bool) $user->fresh()->favorites_only);
    }
    public function test_fallback_uses_first_available_favorite_in_saved_order(): void
    {
        $user = User::factory()->create();
        $user->favorite_models = ['missing/model', 'other/model', 'anthropic/claude-opus-5'];
        $user->save();
        $book = $this->book($user);
        Cache::forever('openrouter.catalog', $this->catalog());
        Http::fake();

        $this->actingAs($user)->get('/books/'.$book->id)->assertOk();
        $this->assertSame('other/model', $user->fresh()->selected_model);
        $this->assertNull($user->fresh()->model_selected_at);

        $this->patchJson('/account', ['favorite_models' => []])->assertOk();
        $this->get('/books/'.$book->id)->assertOk();
        $this->assertNull($user->fresh()->selected_model);
        Http::assertNothingSent();
    }

}
