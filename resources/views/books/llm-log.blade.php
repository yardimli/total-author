@extends('layouts.writer')
@section('title', __('LLM History · ').$book->title)
@section('content')
<main class="llm-log-page history-page">
    <header class="history-hero"><div><div class="eyebrow">{{ __('BOOK HISTORY') }}</div><h1>{{ __('All LLM actions') }}</h1><p>{{ $book->title }} {{ __('· Review usage and full prompts and responses for this book. API keys are never included in request logs.') }}</p></div><a class="history-button" href="{{ route('books.show', $book) }}">{{ __('Back to manuscript') }}</a></header>
    <div class="history-stats">
        @foreach (['actions' => 'Actions', 'prompt_tokens' => __('Prompt tokens'), 'completion_tokens' => __('Completion tokens'), 'cost' => __('Returned cost')] as $key => $label)
        <section><strong>{{ $key === 'cost' ? '$'.\App\Support\Money::display($summary->$key) : ($summary->$key === null ? '—' : number_format($summary->$key)) }}</strong><small>{{ __($label) }}</small></section>
        @endforeach
    </div>
    @if ($summary->recorded < $summary->actions)<p class="history-note">{{ __('Token totals cover') }} {{ $summary->recorded }} {{ __('of') }} {{ $summary->actions }} {{ __('calls with reported usage.') }}</p>@endif
    <form class="history-filters" method="get">
        <label>{{ __('Action') }}<select name="stage"><option value="">{{ __('All actions') }}</option>@foreach($stages as $stage)<option value="{{ $stage }}" @selected(($filters['stage'] ?? '') === $stage)>{{ __($stage) }}</option>@endforeach</select></label>
        <label>{{ __('Status') }}<select name="status"><option value="">{{ __('All statuses') }}</option>@foreach(['reserved','pending','settled','cancelled'] as $status)<option @selected(($filters['status'] ?? '') === $status) value="{{ $status }}">{{ __($status) }}</option>@endforeach</select></label>
        <label>{{ __('API key source') }}<select name="funding"><option value="">{{ __('All key sources') }}</option><option value="personal" @selected(($filters['funding'] ?? '') === 'personal')>{{ __('Personal key') }}</option><option value="demo" @selected(($filters['funding'] ?? '') === 'demo')>{{ __('Demo allowance') }}</option></select></label>
        <button class="primary">{{ __('Apply') }}</button><a class="history-button" href="{{ route('books.llm-log', $book) }}">{{ __('Reset') }}</a>
    </form>
    @forelse ($calls as $call)
    <article class="history-call">
        <header><div><div class="history-call-title"><h2>{{ __($call->stage) }}</h2><span class="history-badge {{ $call->error ? 'failed' : '' }}">{{ $call->error ? __('Error') : __($call->status) }}</span><span class="history-id">#{{ $call->id }}</span></div><p class="muted">{{ $call->model }}</p></div><div class="history-time"><time>{{ $call->created_at->translatedFormat('M j, Y · H:i:s') }}</time><small>{{ $call->created_at->diffForHumans() }}</small></div></header>
        <div class="history-call-stats">@foreach(['prompt_tokens'=>__('Prompt tokens'),'completion_tokens'=>__('Completion tokens'),'total_tokens'=>__('Total tokens'),'cost'=>__('Returned cost')] as $key=>$label)<div><small>{{ __($label) }}</small><strong>{{ $call->$key === null ? '—' : ($key === 'cost' ? '$'.\App\Support\Money::display($call->$key) : number_format($call->$key)) }}</strong></div>@endforeach</div>
        <div class="history-call-meta"><div><small>{{ __('API key source') }}</small>{{ $call->funding === 'personal' ? __('Personal OpenRouter key') : __('Demo allowance') }}</div><div><small>{{ __('Book') }}</small><a href="{{ route('books.show', $book) }}">{{ $book->title }}</a></div><div><small>{{ __('Response') }}</small>{{ $call->response_status ? 'HTTP '.$call->response_status : __('Not received') }}</div><div><small>{{ __('Payload') }}</small>{{ $call->has_request ? __('Request recorded') : __('No recorded request') }} · {{ $call->has_response ? __('Response recorded') : __('No recorded response') }}</div></div>
        <footer><a class="history-button history-open" href="{{ route('books.llm-call', [$book, $call->id]) }}">{{ __('View full prompt & response') }}</a></footer>
    </article>
    @empty <section class="history-call">{{ __('No LLM calls match these filters.') }}</section> @endforelse
    {{ $calls->links() }}
</main>
@endsection
