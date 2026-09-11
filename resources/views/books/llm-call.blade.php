@extends('layouts.writer')
@section('title', 'LLM call #'.$call->id.' · '.$book->title)
@section('content')
<main class="llm-log-page payload-page">
    <a class="field-link" href="{{ route('books.llm-log', $book) }}">← Back to LLM Log</a>
    <div class="eyebrow">EXACT OPENROUTER PAYLOAD</div><h1>Call #{{ $call->id }} · {{ $call->stage }}</h1>
    <p>{{ $call->model }} · {{ $book->title }}</p>
    <p class="muted">{{ $call->created_at }} · {{ $call->response_status ? 'HTTP '.$call->response_status : $call->status }}{{ $call->error ? ' · '.$call->error : '' }}</p>
    <div class="llm-payloads">
        <label><span class="payload-heading">Sent to the LLM<button type="button" data-copy-payload="llm-request">Copy JSON</button></span><textarea id="llm-request" readonly spellcheck="false" wrap="soft">{{ $payload ?? 'No request was recorded for this call. Older calls cannot be reconstructed.' }}</textarea></label>
        <label><span class="payload-heading">Returned by the LLM<button type="button" data-copy-payload="llm-response">Copy JSON</button></span><textarea id="llm-response" readonly spellcheck="false" wrap="soft">{{ $response ?? 'No response was recorded for this call.' }}</textarea></label>
    </div>
</main>
@endsection
