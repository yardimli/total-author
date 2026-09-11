<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use App\Services\Manuscript;
use Illuminate\Database\Seeder;

class DevelopmentProposalSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new \RuntimeException('Local testing only.');
        }
        $user = User::where('email', 'writer@example.test')->firstOrFail();
        $book = Book::where('user_id', $user->id)->firstOrFail();
        if ($book->proposals()->where('status', 'pending')->exists()) {
            return;
        }
        $book->messages()->create(['role' => 'assistant', 'content' => 'Local review fixture: this proposal tests the diff interface without contacting or charging an AI provider.']);
        $book->proposals()->create(['base_revision' => $book->revision, 'changes' => [
            ['operation' => 'manuscript_replace', 'start_block' => 0, 'end_block' => 1, 'before' => Manuscript::text(['content' => [$book->document['content'][0]]]), 'content' => 'Mara Vale returned to the orchard at dusk. The old gate had forgotten the shape of her hand.'],
            ['operation' => 'codex_create', 'name' => 'The Orchard', 'type' => 'Places', 'content' => 'An old orchard to which Mara returns.', 'aliases' => ['orchard'], 'before' => null],
        ]]);
    }
}
