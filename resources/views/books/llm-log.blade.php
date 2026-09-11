@extends('layouts.writer')
@section('title', 'LLM History · '.$book->title)
@section('content')
<main class="llm-log-page history-page">
    <header class="history-hero"><div><div class="eyebrow">BOOK HISTORY</div><h1>All LLM actions</h1><p>{{ $book->title }} · Review usage and full prompts and responses for this book. API keys are never included in request logs.</p></div><a class="history-button" href="{{ route('books.show', $book) }}">Back to manuscript</a></header>
    <div class="history-stats">
        @foreach (['actions' => 'Actions', 'prompt_tokens' => 'Prompt tokens', 'completion_tokens' => 'Completion tokens', 'cost' => 'Returned cost'] as $key => $label)
        <section><strong>{{ $key === 'cost' ? '$'.\App\Support\Money::display($summary->$key) : ($summary->$key === null ? '—' : number_format($summary->$key)) }}</strong><small>{{ $label }}</small></section>
        @endforeach
    </div>
    @if ($summary->recorded < $summary->actions)<p class="history-note">Token totals cover {{ $summary->recorded }} of {{ $summary->actions }} calls with reported usage.</p>@endif
    <form class="history-filters" method="get">
        <label>Action<select name="stage"><option value="">All actions</option>@foreach($stages as $stage)<option value="{{ $stage }}" @selected(($filters['stage'] ?? '') === $stage)>{{ ucfirst($stage) }}</option>@endforeach</select></label>
        <label>Status<select name="status"><option value="">All statuses</option>@foreach(['reserved','pending','settled','cancelled'] as $status)<option @selected(($filters['status'] ?? '') === $status) value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach</select></label>
        <label>API key source<select name="funding"><option value="">All key sources</option><option value="personal" @selected(($filters['funding'] ?? '') === 'personal')>Personal key</option><option value="demo" @selected(($filters['funding'] ?? '') === 'demo')>Demo allowance</option></select></label>
        <button class="primary">Apply</button><a class="history-button" href="{{ route('books.llm-log', $book) }}">Reset</a>
    </form>
    @forelse ($calls as $call)
    <article class="history-call">
        <header><div><div class="history-call-title"><h2>{{ ucfirst($call->stage) }}</h2><span class="history-badge {{ $call->error ? 'failed' : '' }}">{{ $call->error ? 'Error' : ucfirst($call->status) }}</span><span class="history-id">#{{ $call->id }}</span></div><p class="muted">{{ $call->model }}</p></div><div class="history-time"><time>{{ $call->created_at->format('M j, Y · g:i:s A') }}</time><small>{{ $call->created_at->diffForHumans() }}</small></div></header>
        <div class="history-call-stats">@foreach(['prompt_tokens'=>'Prompt tokens','completion_tokens'=>'Completion tokens','total_tokens'=>'Total tokens','cost'=>'Returned cost'] as $key=>$label)<div><small>{{ $label }}</small><strong>{{ $call->$key === null ? '—' : ($key === 'cost' ? '$'.\App\Support\Money::display($call->$key) : number_format($call->$key)) }}</strong></div>@endforeach</div>
        <div class="history-call-meta"><div><small>API key source</small>{{ $call->funding === 'personal' ? 'Personal OpenRouter key' : 'Demo allowance' }}</div><div><small>Book</small><a href="{{ route('books.show', $book) }}">{{ $book->title }}</a></div><div><small>Response</small>{{ $call->response_status ? 'HTTP '.$call->response_status : 'Not received' }}</div><div><small>Payload</small>{{ $call->has_request ? 'Request recorded' : 'No recorded request' }} · {{ $call->has_response ? 'Response recorded' : 'No recorded response' }}</div></div>
        <footer><a class="history-button history-open" href="{{ route('books.llm-call', [$book, $call->id]) }}">View full prompt & response</a></footer>
    </article>
    @empty <section class="history-call">No LLM calls match these filters.</section> @endforelse
    {{ $calls->links() }}
</main>
@endsection
