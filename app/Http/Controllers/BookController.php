<?php

namespace App\Http\Controllers;

use App\Models\AiCall;
use App\Models\Book;
use App\Models\CodexEntry;
use App\Services\Manuscript;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    public function export(Request $request, Book $book, string $format)
    {
        $this->owned($request, $book);
        abort_unless(in_array($format, ['txt', 'docx']), 404);
        $content = $format === 'txt' ? Manuscript::text($book->document) : app(\App\Services\ManuscriptExport::class)->docx($book->document);
        $filename = (\Illuminate\Support\Str::slug($book->title) ?: 'manuscript').'.'.$format;

        return response()->streamDownload(fn () => print ($content), $filename, ['Content-Type' => $format === 'txt' ? 'text/plain; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Cache-Control' => 'private, no-store']);
    }

    public function owned(Request $request, Book $book): void
    {
        abort_unless($book->user_id === $request->user()->id, 404);
    }

    public function index(Request $request)
    {
        $filter = $request->query('filter', 'active');
        abort_unless(in_array($filter, ['active', 'archived', 'deleted']), 422);
        $books = Book::where('user_id', $request->user()->id);
        if ($filter === 'deleted') {
            $books->onlyTrashed();
        } else {
            $books->where('archived', $filter === 'archived');
        }

        return view('books.index', ['books' => $books->latest('updated_at')->get(), 'filter' => $filter]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['title' => 'required|string|max:200']);
        $book = Book::create($data + ['user_id' => $request->user()->id, 'document' => Manuscript::fromText(''), 'codex_types' => ['People', 'Places', 'Items', 'Organizations', 'Events', 'Lore']]);

        return redirect()->route('books.show', $book);
    }

    public function show(Request $request, Book $book)
    {
        $this->owned($request, $book);

        return view('books.show', ['book' => $book]);
    }

    public function state(Request $request, Book $book)
    {
        $this->owned($request, $book);

        return response()->json(['book' => $book, 'entries' => $book->entries()->orderBy('name')->get(),
            'messages' => $book->messages()->orderBy('id')->get(), 'proposals' => $book->proposals()->orderBy('id')->get(),
            'revisions' => $book->revisions()->latest('id')->limit(100)->get(['id', 'label', 'created_at']),
            'usage' => ['book' => AiCall::where('book_id', $book->id)->sum('cost'), 'account' => AiCall::where('user_id', $request->user()->id)->sum('cost'),
                'demo_remaining' => max(0, 1 - $request->user()->demo_spent - $request->user()->demo_reserved), 'pending' => AiCall::where('user_id', $request->user()->id)->whereNull('cost')->sum('reserved')]]);
    }

    public function update(Request $request, Book $book)
    {
        $this->owned($request, $book);
        $data = $request->validate(['revision' => 'required|integer', 'title' => 'sometimes|required|string|max:200', 'document' => 'sometimes|required|array',
            'metadata' => 'sometimes|array', 'metadata.synopsis' => 'nullable|string|max:20000', 'metadata.genre' => 'nullable|string|max:200',
            'metadata.point_of_view' => 'nullable|string|max:200', 'metadata.tense' => 'nullable|string|max:200', 'metadata.style_notes' => 'nullable|string|max:20000',
            'archived' => 'sometimes|boolean', 'codex_types' => 'sometimes|array|min:1|max:50', 'codex_types.*' => 'string|max:80|distinct']);
        if (isset($data['document'])) {
            Manuscript::validate($data['document']);
        }

        return DB::transaction(function () use ($book, $data) {
            $book = Book::lockForUpdate()->findOrFail($book->id);
            Manuscript::checkRevision($book, (int) $data['revision']);
            Manuscript::snapshot($book, isset($data['document']) ? 'Manuscript save' : 'Book settings');
            if (isset($data['codex_types'])) {
                abort_if($book->entries()->whereNotIn('type', $data['codex_types'])->exists(), 422, 'Move entries to another type before removing their type.');
            }
            $book->fill($data);
            if (isset($data['document'])) {
                $book->manuscript = Manuscript::text($data['document']);
            }
            $book->revision++;
            $book->save();

            return ['revision' => $book->revision];
        });
    }

    public function destroy(Request $request, Book $book)
    {
        $this->owned($request, $book);
        $book->delete();

        return redirect()->route('dashboard');
    }

    public function recover(Request $request, int $id)
    {
        $book = Book::withTrashed()->where('user_id', $request->user()->id)->findOrFail($id);
        $book->restore();

        return redirect()->route('dashboard');
    }

    public function llmLog(Request $request, Book $book)
    {
        $this->owned($request, $book);

        return response()->json(AiCall::where('book_id', $book->id)->latest('id')->paginate(30, ['id', 'model', 'stage', 'status', 'cost', 'reserved', 'response_status', 'error', 'created_at']))->header('Cache-Control', 'private, no-store');
    }

    public function llmLogPage(Request $request, Book $book)
    {
        $this->owned($request, $book);
        $filters = $request->validate(['stage' => 'nullable|string|max:100', 'status' => 'nullable|in:reserved,pending,settled,cancelled', 'funding' => 'nullable|in:demo,personal']);
        $query = AiCall::where('book_id', $book->id);
        foreach ($filters as $field => $value) {
            if ($value !== null && $value !== '') {
                $query->where($field, $value);
            }
        }
        $summary = (clone $query)->selectRaw('COUNT(*) AS actions, SUM(prompt_tokens) AS prompt_tokens, SUM(completion_tokens) AS completion_tokens, SUM(cost) AS cost, COUNT(prompt_tokens) AS recorded')->first();
        $stages = AiCall::where('book_id', $book->id)->distinct()->pluck('stage');
        $calls = $query->latest('id')
            ->select(['id', 'model', 'stage', 'status', 'funding', 'cost', 'reserved', 'prompt_tokens', 'completion_tokens', 'total_tokens', 'response_status', 'error', 'created_at'])
            ->selectRaw('request_payload IS NOT NULL AS has_request, response_body IS NOT NULL AS has_response')
            ->paginate(30)->withQueryString();

        return response()->view('books.llm-log', compact('book', 'calls', 'summary', 'stages', 'filters'))->header('Cache-Control', 'private, no-store');
    }

    public function llmCallPage(Request $request, Book $book, int $id)
    {
        $this->owned($request, $book);
        $call = AiCall::where('book_id', $book->id)->findOrFail($id);
        $payload = \App\Support\JsonDisplay::format($call->getRawOriginal('request_payload'));
        $response = \App\Support\JsonDisplay::format($call->getRawOriginal('response_body'));

        return response()->view('books.llm-call', compact('book', 'call', 'payload', 'response'))->header('Cache-Control', 'private, no-store');
    }

    public function llmCall(Request $request, Book $book, int $id)
    {
        $this->owned($request, $book);

        return response()->json(AiCall::where('book_id', $book->id)->findOrFail($id))->header('Cache-Control', 'private, no-store');
    }

    public function deleteMessage(Request $request, Book $book, int $id)
    {
        $this->owned($request, $book);
        $book->messages()->findOrFail($id)->delete();

        return response()->json(['deleted' => true]);
    }

    public function revision(Request $request, Book $book, int $id)
    {
        $this->owned($request, $book);

        return response()->json($book->revisions()->findOrFail($id))->header('Cache-Control', 'private, no-store');
    }

    public function restore(Request $request, Book $book, int $id)
    {
        $this->owned($request, $book);
        $request->validate(['revision' => 'required|integer']);

        return DB::transaction(function () use ($book, $request, $id) {
            $book = Book::lockForUpdate()->findOrFail($book->id);
            Manuscript::checkRevision($book, (int) $request->revision);
            $snapshot = $book->revisions()->findOrFail($id)->snapshot;
            Manuscript::snapshot($book, 'Before revision restore');
            $book->fill(collect($snapshot)->only(['document', 'metadata', 'title', 'codex_types'])->all());
            $book->manuscript = Manuscript::text($book->document);
            $book->revision++;
            $book->save();
            $book->entries()->delete();
            foreach ($snapshot['entries'] as $entry) {
                $book->entries()->create(collect($entry)->only(['id', 'name', 'type', 'content', 'aliases', 'revision'])->all());
            }

            return ['revision' => $book->revision];
        });
    }

    public function entry(Request $request, Book $book, ?int $id = null)
    {
        $this->owned($request, $book);
        $data = $request->validate(['revision' => 'required|integer', 'name' => 'required|string|max:200', 'type' => 'required|string|max:80', 'content' => 'nullable|string|max:100000', 'aliases' => 'nullable|string|max:4000']);

        return DB::transaction(function () use ($book, $data, $id) {
            $book = Book::lockForUpdate()->findOrFail($book->id);
            Manuscript::checkRevision($book, (int) $data['revision']);
            abort_unless(in_array($data['type'], $book->codex_types), 422, 'Add the codex type first.');
            Manuscript::snapshot($book, 'Codex edit');
            $entry = $id ? $book->entries()->findOrFail($id) : new CodexEntry(['book_id' => $book->id]);
            $entry->fill(collect($data)->except('revision')->all());
            $entry->aliases = array_values(array_unique(array_filter(array_map('trim', explode(',', $data['aliases'] ?? '')))));
            $entry->revision = ($entry->revision ?? 0) + 1;
            $entry->save();
            $book->increment('revision');

            return ['entry' => $entry, 'revision' => $book->revision];
        });
    }

    public function deleteEntry(Request $request, Book $book, int $id)
    {
        $this->owned($request, $book);
        $request->validate(['revision' => 'required|integer']);

        return DB::transaction(function () use ($book, $request, $id) {
            $book = Book::lockForUpdate()->findOrFail($book->id);
            Manuscript::checkRevision($book, (int) $request->revision);
            Manuscript::snapshot($book, 'Before codex deletion');
            $book->entries()->findOrFail($id)->delete();
            $book->increment('revision');

            return ['revision' => $book->revision];
        });
    }
}
