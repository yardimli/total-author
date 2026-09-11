<?php

namespace Tests\Feature;

use App\Models\AiCall;
use App\Models\Book;
use App\Models\User;
use App\Services\Manuscript;
use App\Services\OpenRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WritingWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function book(?User $user = null): Book
    {
        $user ??= User::factory()->create();
        $this->actingAs($user);

        return Book::create(['user_id' => $user->id, 'title' => 'The Orchard', 'document' => Manuscript::fromText('Mara waited by the gate.'), 'manuscript' => 'Mara waited by the gate.', 'codex_types' => ['People', 'Places', 'Items']]);
    }

    private function catalog(): array
    {
        $model = ['id' => 'test/writer', 'name' => 'Writer', 'context_length' => 200000, 'pricing' => ['prompt' => '0.000001', 'completion' => '0.000002'], 'architecture' => ['output_modalities' => ['text']]];
        Cache::forever('openrouter.catalog', ['data' => [$model], 'refreshed_at' => now()->toIso8601String()]);
        config(['writer.openrouter_key' => 'demo-test-key']);

        return $model;
    }

    public function test_books_cannot_be_accessed_by_another_account(): void
    {
        $book = $this->book();
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/books/'.$book->id)->assertNotFound();
        $this->patchJson('/api/books/'.$book->id, ['revision' => 1, 'title' => 'Stolen'])->assertNotFound();
        $this->postJson('/api/books/'.$book->id.'/chat', [])->assertNotFound();
    }

    public function test_revision_details_are_scoped_to_book_and_owner(): void
    {
        $book = $this->book();
        Manuscript::snapshot($book, 'Before edit');
        $revision = $book->revisions()->first();
        $url = '/api/books/'.$book->id.'/revisions/'.$revision->id;
        $this->getJson($url)->assertOk()->assertJsonPath('snapshot.title', 'The Orchard');
        $second = $this->book(User::find($book->user_id));
        $this->getJson('/api/books/'.$second->id.'/revisions/'.$revision->id)->assertNotFound();
        $this->actingAs(User::factory()->create());
        $this->getJson($url)->assertNotFound();
    }

    public function test_selection_edit_preserves_unselected_text_and_formatting(): void
    {
        $book = $this->book();
        $this->catalog();
        $doc = Manuscript::fromText('😀 Mara waited by the gate.');
        $doc['content'][0]['content'] = [['type' => 'text', 'text' => '😀 ', 'marks' => [['type' => 'strong']]], ['type' => 'text', 'text' => 'Mara'], ['type' => 'text', 'text' => ' waited by the gate.', 'marks' => [['type' => 'em']]]];
        $book->update(['document' => $doc]);
        Http::fake(['*/chat/completions' => Http::sequence()
            ->push(['usage' => ['cost' => 0], 'choices' => [['message' => ['content' => '{"intent":"manuscript","entry_ids":[],"needs_names":false}']]]])
            ->push(['usage' => ['cost' => 0], 'choices' => [['message' => ['content' => '{"chat_response":"Renamed the selection.","changes":[{"operation":"selection_replace","content":"Élodie"}]}']]]])]);
        $this->postJson('/api/books/'.$book->id.'/chat', ['request_id' => (string) Str::uuid(), 'message' => 'Change the name', 'model' => 'test/writer', 'history' => '0', 'selection' => ['revision' => 1, 'from_block' => 0, 'to_block' => 0, 'from_offset' => 3, 'to_offset' => 7, 'text' => 'Mara']])->assertOk();
        $proposal = $book->proposals()->first();
        $this->postJson('/api/books/'.$book->id.'/proposals/'.$proposal->id, ['accept' => [0]])->assertOk();
        $saved = $book->fresh()->document;
        $this->assertSame('😀 Élodie waited by the gate.', Manuscript::text($saved));
        $this->assertSame($doc['content'][0]['content'][0], $saved['content'][0]['content'][0]);
        $this->assertSame($doc['content'][0]['content'][2], $saved['content'][0]['content'][2]);
    }

    public function test_selection_scope_rejects_whole_paragraph_ai_changes(): void
    {
        $book = $this->book();
        $this->catalog();
        Http::fake(['*/chat/completions' => Http::sequence()
            ->push(['usage' => ['cost' => 0], 'choices' => [['message' => ['content' => '{"intent":"manuscript","entry_ids":[],"needs_names":false}']]]])
            ->push(['usage' => ['cost' => 0], 'choices' => [['message' => ['content' => '{"chat_response":"Changed everything.","changes":[{"operation":"manuscript_replace","start_block":0,"end_block":1,"content":"Wrong"}]}']]]])]);
        $this->postJson('/api/books/'.$book->id.'/chat', ['request_id' => (string) Str::uuid(), 'message' => 'Rename', 'model' => 'test/writer', 'history' => '0', 'selection' => ['revision' => 1, 'from_block' => 0, 'to_block' => 0, 'from_offset' => 0, 'to_offset' => 4, 'text' => 'Mara']])->assertUnprocessable();
        $this->assertSame(0, $book->proposals()->count());
        $this->assertSame('Mara waited by the gate.', $book->fresh()->manuscript);
    }

    public function test_stale_selection_is_rejected_before_any_paid_call(): void
    {
        $book = $this->book();
        $this->catalog();
        Http::fake();
        $this->postJson('/api/books/'.$book->id.'/chat', ['request_id' => (string) Str::uuid(), 'message' => 'Rename', 'model' => 'test/writer', 'history' => '0', 'selection' => ['revision' => 0, 'from_block' => 0, 'to_block' => 0, 'from_offset' => 0, 'to_offset' => 4, 'text' => 'Mara']])->assertStatus(409);
        Http::assertNothingSent();
    }

    public function test_selection_can_cross_paragraphs_without_touching_outside_blocks(): void
    {
        $doc = Manuscript::fromText("Untouched\nBefore middle\nnext after\nAlso untouched");
        $scope = ['from_block' => 1, 'to_block' => 2, 'from_offset' => 7, 'to_offset' => 4, 'text' => "middle\nnext"];
        $saved = \App\Services\SelectionEdit::apply($doc, $scope, "revised\npassage");
        $this->assertSame("Untouched\nBefore revised\npassage after\nAlso untouched", Manuscript::text($saved));
        $this->assertSame($doc['content'][0], $saved['content'][0]);
        $this->assertSame($doc['content'][3], $saved['content'][3]);
    }

    public function test_llm_payloads_are_recorded_and_scoped_without_authentication_headers(): void
    {
        $book = $this->book();
        $model = $this->catalog();
        $messages = [['role' => 'user', 'content' => 'Review this manuscript.']];
        $body = ['id' => 'generation-test', 'usage' => ['cost' => .0001, 'prompt_tokens' => 449, 'completion_tokens' => 364, 'total_tokens' => 813], 'choices' => [['message' => ['content' => '{"chat_response":"Hello","changes":[]}']]]];
        Http::fake(['*/chat/completions' => Http::response($body)]);
        $router = app(OpenRouter::class);
        $call = $router->reserve(User::find($book->user_id), $book, $model, $messages, 'write');
        $router->send(User::find($book->user_id), $call, $model, $messages);
        $detail = $this->getJson('/api/books/'.$book->id.'/llm-log/'.$call->id)->assertOk()->assertJsonPath('request_payload.messages', $messages)->assertJsonPath('response_status', 200);
        $this->assertSame($body, json_decode($detail->json('response_body'), true));
        $detail->assertJsonPath('prompt_tokens', 449)->assertJsonPath('completion_tokens', 364)->assertJsonPath('total_tokens', 813);
        $this->get('/books/'.$book->id.'/llm-log?funding=personal')->assertOk()->assertViewHas('summary', fn ($summary) => $summary->actions === 0);
        $this->get('/books/'.$book->id.'/llm-log?funding=demo')->assertOk()->assertViewHas('summary', fn ($summary) => (int) $summary->prompt_tokens === 449);
        $this->assertStringNotContainsString('demo-test-key', $detail->getContent());
        $this->get('/books/'.$book->id.'/llm-log')->assertOk()->assertSee('Request recorded')->assertSee('Response recorded');
        $this->get('/books/'.$book->id.'/llm-log/'.$call->id)->assertOk()
            ->assertViewHas('payload', fn ($value) => str_contains($value, "\n") && json_decode($value, true)['messages'] === $messages)
            ->assertViewHas('response', fn ($value) => str_contains($value, "\n") && json_decode($value, true) === $body)
            ->assertDontSee('No request was recorded');
        $this->getJson('/api/books/'.$book->id.'/llm-log')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.request_payload');
        $other = $this->book(User::find($book->user_id));
        $this->getJson('/api/books/'.$other->id.'/llm-log/'.$call->id)->assertNotFound();
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/books/'.$book->id.'/llm-log')->assertNotFound();
        $this->getJson('/api/books/'.$book->id.'/llm-log/'.$call->id)->assertNotFound();
        $this->get('/books/'.$book->id.'/llm-log')->assertNotFound();
        $this->get('/books/'.$book->id.'/llm-log/'.$call->id)->assertNotFound();
    }

    public function test_deleted_messages_leave_history_but_keep_request_deduplication(): void
    {
        $book = $this->book();
        $this->catalog();
        $requestId = (string) Str::uuid();
        $message = $book->messages()->create(['role' => 'user', 'content' => 'Remove me', 'request_id' => $requestId]);
        $other = $this->book(User::find($book->user_id));
        $this->deleteJson('/api/books/'.$other->id.'/messages/'.$message->id)->assertNotFound();
        $this->deleteJson('/api/books/'.$book->id.'/messages/'.$message->id)->assertOk();
        $this->getJson('/api/books/'.$book->id)->assertJsonCount(0, 'messages');
        Http::fake();
        $this->postJson('/api/books/'.$book->id.'/chat', ['request_id' => $requestId, 'message' => 'Retry', 'model' => 'test/writer', 'history' => 'all'])->assertStatus(409);
        Http::assertNothingSent();
        $this->assertSoftDeleted('chat_messages', ['id' => $message->id]);
    }

    public function test_library_filters_archive_and_recoverable_deletion(): void
    {
        $book = $this->book();
        $this->get('/dashboard')->assertSee('5 words');
        $other = Book::create(['user_id' => User::factory()->create()->id, 'title' => 'Private manuscript', 'archived' => true, 'codex_types' => ['People'], 'document' => Manuscript::fromText('')]);
        $this->get('/dashboard')->assertOk()->assertSee('The Orchard')->assertDontSee($other->title);
        $this->patchJson('/api/books/'.$book->id, ['revision' => 1, 'archived' => true])->assertOk();
        $this->get('/dashboard')->assertOk()->assertDontSee('The Orchard');
        $this->get('/dashboard?filter=archived')->assertOk()->assertSee('The Orchard')->assertSee('Unarchive')->assertDontSee($other->title);
        $this->patchJson('/api/books/'.$book->id, ['revision' => 2, 'archived' => false])->assertOk();
        $this->get('/dashboard')->assertSee('The Orchard');
        $this->delete('/books/'.$book->id)->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertDontSee('The Orchard');
        $this->get('/dashboard?filter=deleted')->assertOk()->assertSee('The Orchard')->assertSee('Recover book');
        $this->post('/books/'.$book->id.'/recover')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertSee('The Orchard');
    }

    public function test_saves_are_versioned_and_stale_writes_are_rejected(): void
    {
        $book = $this->book();
        $url = '/api/books/'.$book->id;
        $this->patchJson($url, ['revision' => 1, 'document' => Manuscript::fromText('New paragraph.')])->assertOk()->assertJsonPath('revision', 2);
        $this->patchJson($url, ['revision' => 1, 'document' => Manuscript::fromText('Stale overwrite')])->assertStatus(409);
        $this->assertSame('New paragraph.', $book->fresh()->manuscript);
        $this->assertSame(1, $book->revisions()->count());
    }

    public function test_formatted_manuscript_whitespace_is_preserved_and_nested_documents_rejected(): void
    {
        $book = $this->book();
        $doc = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Mara '], ['type' => 'text', 'text' => 'waited', 'marks' => [['type' => 'em']]], ['type' => 'text', 'text' => ' by the gate.']]]]];
        $this->patchJson('/api/books/'.$book->id, ['revision' => 1, 'document' => $doc])->assertOk();
        $this->assertSame('Mara waited by the gate.', $book->fresh()->manuscript);
        $this->assertSame($doc, $book->fresh()->document);
        $this->patchJson('/api/books/'.$book->id, ['revision' => 2, 'document' => ['type' => 'doc', 'content' => [$doc]]])->assertStatus(422);
    }

    public function test_raw_email_control_characters_are_rejected_before_trimming(): void
    {
        $this->postJson('/register', ['name' => 'Invalid', 'email' => "test@example.test\r\n", 'password' => 'password', 'password_confirmation' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_reconciliation_is_idempotent(): void
    {
        $book = $this->book();
        $model = $this->catalog();
        $router = app(OpenRouter::class);
        $user = User::find($book->user_id);
        $call = $router->reserve($user, $book, $model, [['role' => 'user', 'content' => 'Test']], 'execution');
        $call->update(['status' => 'pending', 'provider_id' => 'generation-test']);
        Http::fake(['*/generation*' => Http::response(['data' => ['total_cost' => .003]])]);
        $this->assertTrue($router->reconcile($call));
        $this->assertFalse($router->reconcile($call->fresh()));
        $this->assertEquals(.003, (float) $user->fresh()->demo_spent);
        $this->assertEquals(0, (float) $user->fresh()->demo_reserved);
    }

    public function test_codex_aliases_types_and_restore_keep_reference_ids(): void
    {
        $book = $this->book();
        $url = '/api/books/'.$book->id;
        $entry = $this->postJson($url.'/entries', ['revision' => 1, 'name' => 'Mara Vale', 'type' => 'People', 'aliases' => ' Mara, , Captain, Mara ', 'content' => 'A traveler.'])->assertOk()->json('entry');
        $this->assertSame(['Mara', 'Captain'], $entry['aliases']);
        $this->patchJson($url, ['revision' => 2, 'codex_types' => ['Places']])->assertStatus(422);
        $this->postJson($url.'/entries/'.$entry['id'], ['revision' => 2, 'name' => 'Mara Vale', 'type' => 'People', 'aliases' => 'Mara', 'content' => 'Changed.'])->assertOk();
        $snapshot = $book->revisions()->latest('id')->first();
        $this->postJson($url.'/revisions/'.$snapshot->id.'/restore', ['revision' => 3])->assertOk();
        $this->assertDatabaseHas('codex_entries', ['id' => $entry['id'], 'content' => 'A traveler.']);
    }

    public function test_names_use_full_countries_and_filter_local_datasets(): void
    {
        $this->actingAs(User::factory()->create());
        $countries = $this->getJson('/api/countries')->assertOk()->json();
        $this->assertSame('United States', collect($countries)->firstWhere('code', 'US')['name']);
        $this->getJson('/api/names?country=US&gender=Female&q=Maria')->assertOk()->assertJsonPath('first.0.name', 'Maria');
        $this->getJson('/api/names?country=US&last_country=BR&random=1')->assertOk()->assertJsonCount(10, 'results');
        $this->getJson('/api/names?country=../')->assertStatus(422);
    }

    public function test_exports_are_owned_downloads_and_docx_contains_clean_unicode_text(): void
    {
        $book = $this->book();
        $doc = Manuscript::fromText('Mara & Élodie <returned>.');
        $doc['content'][0]['content'][0]['marks'] = [['type' => 'em']];
        $book->update(['document' => $doc]);
        $this->get('/books/'.$book->id.'/export/txt')->assertOk()->assertDownload('the-orchard.txt')->assertStreamedContent('Mara & Élodie <returned>.');
        $response = $this->get('/books/'.$book->id.'/export/docx')->assertOk()->assertDownload('the-orchard.docx');
        $path = tempnam(sys_get_temp_dir(), 'writer-test-');
        try {
            file_put_contents($path, $response->streamedContent());
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path));
            $xml = $zip->getFromName('word/document.xml');
            $parsed = new \DOMDocument;
            $this->assertTrue($parsed->loadXML($xml));
            $this->assertStringContainsString('Mara & Élodie <returned>.', $parsed->textContent);
            $this->assertStringContainsString('<w:i/>', $xml);
            $this->assertStringNotContainsString('codex-reference', $xml);
            $zip->close();
        } finally {
            unlink($path);
        }
        $this->actingAs(User::factory()->create())->get('/books/'.$book->id.'/export/docx')->assertNotFound();
    }

    public function test_keys_are_encrypted_and_preferences_persist(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->patchJson('/account', ['openrouter_key' => 'private-test-key', 'selected_model' => 'a/model', 'favorite_models' => ['a/model'], 'theme' => 'dark'])->assertOk();
        $this->assertNotSame('private-test-key', DB::table('users')->where('id', $user->id)->value('openrouter_key'));
        $this->assertSame('private-test-key', $user->fresh()->openrouter_key);
        $this->assertArrayNotHasKey('openrouter_key', $user->fresh()->toArray());
        $this->assertSame('a/model', $user->fresh()->selected_model);
    }

    public function test_catalog_only_refreshes_once_per_login_flag(): void
    {
        $this->actingAs(User::factory()->create());
        Http::fake(['*/models' => Http::response(['data' => []])]);
        $this->withSession(['refresh_model_catalog' => true])->getJson('/api/models')->assertOk();
        $this->getJson('/api/models')->assertOk();
        $this->getJson('/api/models')->assertOk();
        Http::assertSentCount(1);
    }

    public function test_demo_reservations_prevent_overspend_and_unknown_costs_stay_reserved(): void
    {
        $book = $this->book();
        $model = $this->catalog();
        $user = User::find($book->user_id);
        $user->demo_spent = .99999;
        $user->save();
        $router = app(OpenRouter::class);
        try {
            $router->reserve($user, $book, $model, [['role' => 'user', 'content' => 'Hello']], 'classification');
            $this->fail('Budget should reject');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $user->demo_spent = 0;
        $user->save();
        $call = $router->reserve($user, $book, $model, [['role' => 'user', 'content' => 'Hello']], 'classification');
        Http::fake(['*/chat/completions' => Http::response(['error' => ['message' => 'Unknown']], 502)]);
        try {
            $router->send($user, $call, $model, []);
        } catch (\Illuminate\Validation\ValidationException $e) {
        }
        $this->assertGreaterThan(0, (float) $user->fresh()->demo_reserved);
        $this->assertNull($call->fresh()->cost);
    }

    public function test_two_stage_chat_uses_light_classification_and_zero_history_and_only_proposes_changes(): void
    {
        $book = $this->book();
        $this->catalog();
        $book->messages()->create(['role' => 'user', 'content' => 'SECRET HISTORY']);
        $book->messages()->create(['role' => 'assistant', 'content' => 'OLD ANSWER']);
        $book->entries()->create(['name' => 'Gate', 'type' => 'Places', 'content' => 'SECRET CODEX BODY', 'aliases' => []]);
        Http::fake(['*/chat/completions' => Http::sequence()->push(['id' => 'one', 'usage' => ['cost' => .001], 'choices' => [['message' => ['content' => json_encode(['intent' => 'manuscript', 'entry_ids' => [], 'needs_names' => false])]]]])->push(['id' => 'two', 'usage' => ['cost' => .002], 'choices' => [['message' => ['content' => json_encode(['chat_response' => 'A suggested revision.', 'changes' => [['operation' => 'manuscript_replace', 'start_block' => 0, 'end_block' => 1, 'content' => 'Mara opened the gate.']]])]]]])]);
        $this->postJson('/api/books/'.$book->id.'/chat', ['request_id' => (string) Str::uuid(), 'message' => 'Edit the opening.', 'model' => 'test/writer', 'history' => '0'])->assertOk();
        $sent = Http::recorded();
        $first = json_encode($sent[0][0]->data());
        $second = json_encode($sent[1][0]->data());
        $this->assertStringNotContainsString('SECRET HISTORY', $first);
        $this->assertStringNotContainsString('SECRET HISTORY', $second);
        $this->assertStringNotContainsString('SECRET CODEX BODY', $first);
        $this->assertStringNotContainsString('Mara waited', $first);
        $this->assertStringContainsString('SECRET CODEX BODY', $second);
        $this->assertStringContainsString('Mara waited', $second);
        $this->assertSame('Mara waited by the gate.', $book->fresh()->manuscript);
        $proposal = $book->proposals()->first();
        $this->assertNotNull($proposal);
        $this->assertSame($book->messages()->where('role', 'assistant')->latest('id')->first()->id, $proposal->chat_message_id);
        $this->postJson('/api/books/'.$book->id.'/proposals/'.$proposal->id, ['accept' => [0]])->assertOk();
        $this->assertSame('Mara opened the gate.', $book->fresh()->manuscript);
        $this->postJson('/api/books/'.$book->id.'/proposals/'.$proposal->id, ['accept' => [0]])->assertStatus(409);
        $this->assertEquals(.003, (float) User::find($book->user_id)->demo_spent);
    }

    public function test_stale_proposals_cannot_overwrite_newer_work_but_can_be_rejected(): void
    {
        $book = $this->book();
        $proposal = $book->proposals()->create(['base_revision' => 1, 'changes' => [['operation' => 'manuscript_replace', 'start_block' => 0, 'end_block' => 1, 'content' => 'Wrong']]]);
        $book->increment('revision');
        $this->postJson('/api/books/'.$book->id.'/proposals/'.$proposal->id, ['accept' => [0]])->assertStatus(409);
        $this->postJson('/api/books/'.$book->id.'/proposals/'.$proposal->id, ['accept' => []])->assertOk();
        $this->assertSame('Mara waited by the gate.', $book->fresh()->manuscript);
    }

    public function test_missing_person_names_stops_before_second_paid_call(): void
    {
        $book = $this->book();
        $this->catalog();
        Http::fake(['*/chat/completions' => Http::response(['usage' => ['cost' => .001], 'choices' => [['message' => ['content' => '{"intent":"codex","entry_ids":[],"needs_names":true}']]]])]);
        $this->postJson('/api/books/'.$book->id.'/chat', ['request_id' => (string) Str::uuid(), 'message' => 'Create ten people', 'model' => 'test/writer', 'history' => 'all'])->assertOk()->assertJsonPath('needs_names', true);
        Http::assertSentCount(1);
        $this->assertSame(0, $book->proposals()->count());
        $this->assertEquals(0, (float) User::find($book->user_id)->demo_reserved);
    }

    public function test_place_suggestions_are_saved_for_selection_without_codex_writes(): void
    {
        $book = $this->book();
        $this->catalog();
        $names = array_map(fn ($n) => 'Harbor '.$n, range(1, 10));
        Http::fake(['*/chat/completions' => Http::sequence()->push(['usage' => ['cost' => 0], 'choices' => [['message' => ['content' => '{"intent":"places","entry_ids":[],"needs_names":false}']]]])->push(['usage' => ['cost' => 0], 'choices' => [['message' => ['content' => json_encode(['chat_response' => 'Ten coastal names.', 'changes' => [], 'suggestions' => $names])]]]])]);
        $this->postJson('/api/books/'.$book->id.'/chat', ['request_id' => (string) Str::uuid(), 'message' => 'Suggest coastal names', 'action' => 'places', 'country' => 'United States', 'model' => 'test/writer', 'history' => '0'])->assertOk();
        $this->assertSame($names, $book->messages()->latest('id')->first()->suggestions);
        $this->assertSame(0, $book->entries()->count());
    }

    public function test_partial_batch_approval_only_writes_selected_entries_and_retries_do_not_duplicate(): void
    {
        $book = $this->book();
        $proposal = $book->proposals()->create(['base_revision' => 1, 'changes' => array_map(fn ($name) => ['operation' => 'codex_create', 'name' => $name, 'type' => 'Items', 'content' => 'A test item.', 'aliases' => [], 'before' => null], ['Compass', 'Lantern'])]);
        $this->postJson('/api/books/'.$book->id.'/proposals/'.$proposal->id, ['accept' => [1]])->assertOk();
        $this->assertSame(['Lantern'], $book->entries()->pluck('name')->all());
        $this->getJson('/api/books/'.$book->id)->assertOk()->assertJsonPath('proposals.0.status', 'approved')->assertJsonPath('proposals.0.decisions', [1]);
        $this->postJson('/api/books/'.$book->id.'/proposals/'.$proposal->id, ['accept' => [1]])->assertStatus(409);
        $this->assertSame(1, $book->entries()->count());
    }

    public function test_model_failure_preserves_document_and_reused_request_id_does_not_call_again(): void
    {
        $book = $this->book();
        $this->catalog();
        $id = (string) Str::uuid();
        Http::fake(['*/chat/completions' => Http::response(['usage' => ['cost' => .001], 'choices' => [['message' => ['content' => 'Not JSON']]]])]);
        $request = ['request_id' => $id, 'message' => 'Edit this book', 'model' => 'test/writer', 'history' => '0'];
        $this->postJson('/api/books/'.$book->id.'/chat', $request)->assertUnprocessable();
        $this->postJson('/api/books/'.$book->id.'/chat', $request)->assertStatus(409);
        Http::assertSentCount(1);
        $this->assertSame('Mara waited by the gate.', $book->fresh()->manuscript);
    }

    public function test_batch_and_image_generation_models_are_rejected_before_payment(): void
    {
        $book = $this->book();
        $model = $this->catalog();
        Http::fake();
        foreach ([['name' => 'Writer (BATCH)'], ['architecture' => ['output_modalities' => ['text', 'image']]]] as $unsupported) {
            Cache::forever('openrouter.catalog', ['data' => [array_replace($model, $unsupported)], 'refreshed_at' => null]);
            $this->postJson('/api/books/'.$book->id.'/chat', ['request_id' => (string) Str::uuid(), 'message' => 'Edit', 'model' => 'test/writer', 'history' => 'all'])->assertUnprocessable()->assertJsonValidationErrors('model');
        }
        Http::assertNothingSent();
        $this->assertSame(0, AiCall::count());
    }

    public function test_optional_search_fees_and_pricing_tiers_allow_text_requests(): void
    {
        $book = $this->book();
        $model = $this->catalog();
        $model['pricing']['web_search'] = '0.01';
        $model['pricing']['overrides'] = [['min_prompt_tokens' => 272000, 'prompt' => '0.000004', 'completion' => '0.000006']];
        Cache::forever('openrouter.catalog', ['data' => [$model], 'refreshed_at' => null]);
        Http::fake(['*/chat/completions' => Http::sequence()
            ->push(['usage' => ['cost' => .0001], 'choices' => [['message' => ['content' => '{"intent":"conversation","entry_ids":[],"needs_names":false}']]]])
            ->push(['usage' => ['cost' => .0002], 'choices' => [['message' => ['content' => '{"chat_response":"Hello","changes":[]}']]]])]);
        $this->postJson('/api/books/'.$book->id.'/chat', ['request_id' => (string) Str::uuid(), 'message' => 'Hello', 'model' => 'test/writer', 'history' => '0'])->assertOk();
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['provider']['max_price'] == ['prompt' => 4, 'completion' => 6] && $request['plugins'] === [] && ! isset($request['tools']));
        $this->assertEquals(.0003, (float) User::find($book->user_id)->demo_spent);
        $this->assertEquals(0, (float) User::find($book->user_id)->demo_reserved);
    }

    public function test_text_fees_and_tiers_are_reserved_before_payment(): void
    {
        $book = $this->book();
        $model = $this->catalog();
        $model['pricing'] = ['prompt' => 0, 'completion' => 0, 'web_search' => 100,
            'request' => .02, 'input_cache_write' => .000001, 'internal_reasoning' => .000002,
            'overrides' => [['utc_start' => '0900', 'utc_end' => '1700', 'request' => .03]]];
        $messages = [['role' => 'user', 'content' => 'Hello']];
        $call = app(OpenRouter::class)->reserve(User::find($book->user_id), $book, $model, $messages, 'classify');
        $input = strlen(json_encode($messages)) + 64 + 1024;
        $this->assertEqualsWithDelta(.03 + $input * .000001 + config('writer.max_output_tokens') * .000002, (float) $call->reserved, .000000011);
        app(OpenRouter::class)->release($call);
        $model['pricing']['overrides'][0]['request'] = 1.01;
        Cache::forever('openrouter.catalog', ['data' => [$model], 'refreshed_at' => null]);
        Http::fake();
        $this->postJson('/api/books/'.$book->id.'/chat', ['request_id' => (string) Str::uuid(), 'message' => 'Hello', 'model' => 'test/writer', 'history' => '0'])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_context_overflow_is_rejected_before_any_paid_request(): void
    {
        $book = $this->book();
        $model = $this->catalog();
        $model['context_length'] = 10;
        Cache::forever('openrouter.catalog', ['data' => [$model], 'refreshed_at' => null]);
        Http::fake();
        $this->postJson('/api/books/'.$book->id.'/chat', ['request_id' => (string) Str::uuid(), 'message' => 'Edit', 'model' => 'test/writer', 'history' => 'all'])->assertStatus(422);
        Http::assertNothingSent();
        $this->assertSame(0, AiCall::count());
    }

    public function test_scan_can_extract_existing_person_names_without_generating_them(): void
    {
        $book = $this->book();
        $this->catalog();
        Http::fake(['*/chat/completions' => Http::sequence()->push(['usage' => ['cost' => 0], 'choices' => [['message' => ['content' => '{"intent":"scan","entry_ids":[],"needs_names":false}']]]])->push(['usage' => ['cost' => 0], 'choices' => [['message' => ['content' => json_encode(['chat_response' => 'Found Mara.', 'changes' => [['operation' => 'codex_create', 'name' => 'Mara', 'type' => 'People', 'content' => 'Waited at the gate.', 'aliases' => []]]])]]]])]);
        $this->postJson('/api/books/'.$book->id.'/chat', ['request_id' => (string) Str::uuid(), 'message' => 'Scan the story', 'action' => 'scan', 'model' => 'test/writer', 'history' => 'all'])->assertOk();
        $this->assertSame(0, $book->entries()->count());
        $this->assertSame('Mara', $book->proposals()->first()->changes[0]['name']);
    }

    public function test_cursor_focus_and_selection_context_have_different_manuscript_scopes(): void
    {
        $book = $this->book()->fresh();
        $this->catalog();
        $paragraphs = array_map(fn ($i) => "Sentence{$i} has several ordinary words in this complete sentence.", range(0, 400));
        $book->update(['document' => Manuscript::fromText(implode("\n", $paragraphs))]);
        $book->entries()->create(['name' => 'Gate', 'type' => 'Places', 'content' => 'COMPLETE CODEX DETAILS', 'aliases' => []]);
        Http::fake(function ($request) {
            $result = str_starts_with($request['messages'][0]['content'], 'Classify')
                ? ['intent' => 'conversation', 'entry_ids' => [], 'needs_names' => false]
                : ['chat_response' => 'Reviewed', 'changes' => []];

            return Http::response(['usage' => ['cost' => 0], 'choices' => [['message' => ['content' => json_encode($result)]]]]);
        });
        foreach ([false, true] as $selected) {
            $request = ['request_id' => (string) Str::uuid(), 'message' => 'Review here', 'model' => 'test/writer', 'history' => '0', 'cursor' => ['revision' => $book->revision, 'block' => 200, 'offset' => 11]];
            if ($selected) {
                $request['selection'] = ['revision' => $book->revision, 'from_block' => 200, 'to_block' => 200, 'from_offset' => 0, 'to_offset' => 11, 'text' => 'Sentence200'];
            }
            $this->postJson('/api/books/'.$book->id.'/chat', $request)->assertOk();
            $sent = Http::recorded();
            $messages = $sent[count($sent) - 1][0]['messages'];
            $context = json_decode(end($messages)['content'], true);
            $this->assertSame('COMPLETE CODEX DETAILS', $context['entries'][0]['content']);
            if ($selected) {
                $this->assertArrayNotHasKey('blocks', $context);
                $this->assertArrayNotHasKey('cursor_focus', $context);
                $this->assertStringNotContainsString('Sentence0 ', json_encode($context));
                $this->assertStringNotContainsString('Sentence400 ', json_encode($context));
                $window = $context['selection_context'];
                $this->assertGreaterThanOrEqual(500, str_word_count($window['before']));
                $this->assertLessThan(530, str_word_count($window['before']));
            } else {
                $this->assertSame($paragraphs, $context['blocks']);
                $window = $context['cursor_focus'];
                $this->assertStringNotContainsString('Sentence0 ', json_encode($window));
                $this->assertStringNotContainsString('Sentence400 ', json_encode($window));
                $this->assertGreaterThanOrEqual(1000, str_word_count($window['before']));
                $this->assertLessThan(1030, str_word_count($window['before']));
                $this->assertStringContainsString('NOT an editing boundary', $messages[0]['content']);
            }
            $this->assertMatchesRegularExpression('/^Sentence[0-9]+ /', $window['before']);
            $this->assertMatchesRegularExpression('/sentence\.\s*$/', $window['after']);
            $this->assertStringNotContainsString('Sentence200', json_encode($sent[count($sent) - 2][0]->data()));
        }
    }

    public function test_context_window_preserves_unicode_and_expands_partial_sentences(): void
    {
        $doc = Manuscript::fromText('Earlier. 😀 Mara waited beside the gate. Later.');
        $window = \App\Services\ManuscriptContext::window($doc, ['block' => 0, 'offset' => 12], ['block' => 0, 'offset' => 16], 1);
        $this->assertSame('😀 ', $window['before']);
        $this->assertSame('Mara', $window['text']);
        $this->assertSame(' waited beside the gate. ', $window['after']);
    }

    public function test_stale_cursor_is_rejected_before_llm_calls(): void
    {
        $book = $this->book()->fresh();
        Http::fake();
        $this->postJson('/api/books/'.$book->id.'/chat', ['request_id' => (string) Str::uuid(), 'message' => 'Review here', 'model' => 'test/writer', 'history' => '0', 'cursor' => ['revision' => $book->revision + 1, 'block' => 0, 'offset' => 0]])->assertStatus(409);
        Http::assertNothingSent();
    }

    public function test_large_prompt_warning_precedes_payment_and_can_be_forced_or_snoozed(): void
    {
        $book = $this->book()->fresh();
        $this->catalog();
        // Stay within the fake model's context and demo budget while exceeding 30k words.
        $book->update(['document' => Manuscript::fromText(str_repeat('A b c. ', 11000))]);
        Http::fake(function ($request) {
            $result = str_starts_with($request['messages'][0]['content'], 'Classify')
                ? ['intent' => 'conversation', 'entry_ids' => [], 'needs_names' => false]
                : ['chat_response' => 'Reviewed', 'changes' => []];

            return Http::response(['usage' => ['cost' => 0], 'choices' => [['message' => ['content' => json_encode($result)]]]]);
        });
        $payload = ['request_id' => (string) Str::uuid(), 'message' => 'Review', 'model' => 'test/writer', 'history' => '0'];
        $response = $this->postJson('/api/books/'.$book->id.'/chat', $payload)->assertOk()->assertJsonPath('large_prompt_warning', true);
        $this->assertGreaterThan(30000, $response->json('word_count'));
        Http::assertNothingSent();
        $this->assertSame(0, AiCall::count());
        $this->assertSame(0, $book->messages()->count());
        $this->postJson('/api/books/'.$book->id.'/chat', $payload + ['force_large_prompt' => true, 'disable_large_prompt_warning' => true])->assertOk()->assertJsonPath('ok', true);
        Http::assertSentCount(2);
        $payload['request_id'] = (string) Str::uuid();
        $this->travel(59)->minutes();
        $this->postJson('/api/books/'.$book->id.'/chat', $payload)->assertOk()->assertJsonPath('ok', true);
        Http::assertSentCount(4);
        $this->travel(2)->minutes();
        $payload['request_id'] = (string) Str::uuid();
        $this->postJson('/api/books/'.$book->id.'/chat', $payload)->assertOk()->assertJsonPath('large_prompt_warning', true);
        Http::assertSentCount(4);
        $this->travelBack();
    }
}
