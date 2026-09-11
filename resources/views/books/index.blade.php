@extends('layouts.writer')
@section('title', 'Your library · '.config('app.name'))
@section('content')
<main class="library">
    <div class="eyebrow">THE WRITING LIFE</div>
    <div class="library-heading"><div><h1>A room for your stories.</h1><p class="muted">Return to a world you know. Or begin somewhere new.</p></div><span class="ornament">❧</span></div>
    <form action="{{ route('books.store') }}" method="post" class="new-book">@csrf<label for="book-title">Your next book</label><div class="row"><input id="book-title" name="title" placeholder="Every story begins with a title…" maxlength="200" required><button class="primary">Begin a book <span>↗</span></button></div></form>
    <nav class="library-filters" aria-label="Filter books">
        @foreach (['active' => 'On your desk', 'archived' => 'Archived books', 'deleted' => 'Recently deleted'] as $value => $label)
        <a href="{{ route('dashboard', ['filter' => $value]) }}" @if ($filter === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <h2 class="section-heading">{{ ['active' => 'On your desk', 'archived' => 'Archived books', 'deleted' => 'Recently deleted'][$filter] }} <span>{{ $books->count() }} manuscripts</span></h2>
    <div class="book-grid">
    @forelse ($books as $book)
        <article class="book-card {{ $book->trashed() || $book->archived ? 'book-muted' : '' }}">
            <div class="book-spine"></div><div class="eyebrow">{{ $book->trashed() ? 'RECENTLY DELETED' : ($book->archived ? 'ARCHIVED' : 'MANUSCRIPT') }}</div>
            <h2>{{ $book->title }}</h2><p class="muted">{{ $book->metadata['genre'] ?? 'A work in progress' }}</p>
            <p class="book-word-count">{{ number_format($book->word_count) }} words</p>
            <div class="book-bottom"><small>Last opened {{ $book->updated_at->diffForHumans() }}</small>
            @if ($book->trashed())<form method="post" action="{{ route('books.recover',$book->id) }}">@csrf<button>Recover book</button></form>
            @else<a href="{{ route('books.show',$book) }}">Open manuscript ↗</a>@endif</div>
            @unless ($book->trashed())
            <div class="book-file-actions" data-book-files="{{ $book->id }}">
                <button type="button" data-import-book>Import</button>
                <select aria-label="Export format for {{ $book->title }}"><option value="txt">TXT</option><option value="docx">DOCX</option></select>
                <button type="button" data-export-book>Export ↗</button>
            </div>
            <div class="book-manage-actions">
                <button type="button" data-archive-book="{{ $book->id }}" data-revision="{{ $book->revision }}" data-archived="{{ $book->archived ? '1' : '0' }}">{{ $book->archived ? 'Unarchive' : 'Archive' }}</button>
                <form method="post" action="{{ route('books.destroy', $book) }}" data-delete-book>@csrf @method('DELETE')<button type="submit">Delete book</button></form>
            </div>
            @endunless
        </article>
    @empty <div class="empty-library"><span>Ⅰ</span><h2>{{ $filter === 'active' ? 'The first page is waiting.' : 'No books here.' }}</h2><p>{{ $filter === 'active' ? 'Give your book a title above. You can always change it later.' : 'Books in this category will appear here.' }}</p></div> @endforelse
    </div>
</main>
<dialog id="library-import-dialog"><div class="dialog-heading"><h2>Import your story</h2><button type="button" id="library-cancel-import" aria-label="Close import">×</button></div><p>Preview before replacing this book’s manuscript. A revision preserves the previous text.</p><input id="library-import-file" type="file" accept=".txt,.docx"><textarea id="library-import-preview" rows="14" aria-label="Imported text preview"></textarea><div class="row"><button id="library-confirm-import" class="primary" disabled>Replace manuscript</button></div></dialog>
@endsection
