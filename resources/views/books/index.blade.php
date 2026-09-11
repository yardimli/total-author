@extends('layouts.writer')
@section('title', __('Your library · ').config('app.name'))
@section('content')
<main class="library">
    @if(auth()->user()->is_admin && !session()->has('impersonator_id'))<a class="history-button" href="{{ route('admin.users') }}">{{ __('Admin') }}</a>@endif
    <div class="eyebrow">{{ __('THE WRITING LIFE') }}</div>
    <div class="library-heading"><div><h1>{{ __('A room for your stories.') }}</h1><p class="muted">{{ __('Return to a world you know. Or begin somewhere new.') }}</p></div><span class="ornament">❧</span></div>
    <form action="{{ route('books.store') }}" method="post" class="new-book">@csrf<label for="book-title">{{ __('Your next book') }}</label><div class="row"><input id="book-title" name="title" placeholder="{{ __('Every story begins with a title…') }}" maxlength="200" required><button class="primary">{{ __('Begin a book') }} <span>↗</span></button></div></form>
    <nav class="library-filters" aria-label="{{ __('Filter books') }}">
        @foreach (['active' => __('On your desk'), 'archived' => __('Archived books'), 'deleted' => __('Recently deleted')] as $value => $label)
        <a href="{{ route('dashboard', ['filter' => $value]) }}" @if ($filter === $value) aria-current="page" @endif>{{ __($label) }}</a>
        @endforeach
    </nav>
    <h2 class="section-heading">{{ ['active' => __('On your desk'), 'archived' => __('Archived books'), 'deleted' => __('Recently deleted')][$filter] }} <span>{{ $books->count() }} {{ __('manuscripts') }}</span></h2>
    <div class="book-grid">
    @forelse ($books as $book)
        <article class="book-card {{ $book->trashed() || $book->archived ? 'book-muted' : '' }}">
            <div class="book-spine"></div><div class="eyebrow">{{ $book->trashed() ? __('RECENTLY DELETED') : ($book->archived ? __('ARCHIVED') : __('MANUSCRIPT')) }}</div>
            <h2>{{ $book->title }}</h2><p class="muted">{{ $book->metadata['genre'] ?? __('A work in progress') }}</p>
            <p class="book-word-count">{{ number_format($book->word_count) }} {{ __('words') }}</p>
            <div class="book-bottom"><small>{{ __('Last opened') }} {{ $book->updated_at->diffForHumans() }}</small>
            @if ($book->trashed())<form method="post" action="{{ route('books.recover',$book->id) }}">@csrf<button>{{ __('Recover book') }}</button></form>
            @else<a href="{{ route('books.show',$book) }}">{{ __('Open manuscript ↗') }}</a>@endif</div>
            @unless ($book->trashed())
            <div class="book-file-actions" data-book-files="{{ $book->id }}">
                <button type="button" data-import-book>{{ __('Import') }}</button>
                <select aria-label="{{ __('Export format for :book', ['book' => $book->title]) }}"><option value="txt">TXT</option><option value="docx">DOCX</option></select>
                <button type="button" data-export-book>{{ __('Export ↗') }}</button>
            </div>
            <div class="book-manage-actions">
                <button type="button" data-archive-book="{{ $book->id }}" data-revision="{{ $book->revision }}" data-archived="{{ $book->archived ? '1' : '0' }}">{{ $book->archived ? __('Unarchive') : __('Archive') }}</button>
                <form method="post" action="{{ route('books.destroy', $book) }}" data-delete-book>@csrf @method('DELETE')<button type="submit">{{ __('Delete book') }}</button></form>
            </div>
            @endunless
        </article>
    @empty <div class="empty-library"><span>Ⅰ</span><h2>{{ $filter === 'active' ? __('The first page is waiting.') : __('No books here.') }}</h2><p>{{ $filter === 'active' ? __('Give your book a title above. You can always change it later.') : __('Books in this category will appear here.') }}</p></div> @endforelse
    </div>
</main>
<dialog id="library-import-dialog"><div class="dialog-heading"><h2>{{ __('Import your story') }}</h2><button type="button" id="library-cancel-import" aria-label="{{ __('Close import') }}">×</button></div><p>{{ __('Preview before replacing this book’s manuscript. A revision preserves the previous text.') }}</p><input id="library-import-file" type="file" accept=".txt,.docx"><textarea id="library-import-preview" rows="14" aria-label="{{ __('Imported text preview') }}"></textarea><div class="row"><button id="library-confirm-import" class="primary" disabled>{{ __('Replace manuscript') }}</button></div></dialog>
@endsection
