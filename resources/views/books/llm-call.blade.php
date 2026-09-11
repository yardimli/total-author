@extends('layouts.writer')
@section('title', __('LLM call #').$call->id.' · '.$book->title)
@section('content')
<main class="llm-log-page payload-page">
    <a class="field-link" href="{{ route('books.llm-log', $book) }}">{{ __('← Back to LLM Log') }}</a>
    <div class="eyebrow">{{ __('EXACT OPENROUTER PAYLOAD') }}</div><h1>{{ __('Call #') }}{{ $call->id }} · {{ __($call->stage) }}</h1>
    <p>{{ $call->model }} · {{ $book->title }}</p>
    <p class="muted">{{ $call->created_at }} · {{ $call->response_status ? 'HTTP '.$call->response_status : __($call->status) }}{{ $call->error ? ' · '.__($call->error) : '' }}</p>
    <div class="llm-payloads">
        <label><span class="payload-heading">{{ __('Sent to the LLM') }}<button type="button" data-copy-payload="llm-request">{{ __('Copy JSON') }}</button></span><textarea id="llm-request" readonly spellcheck="false" wrap="soft">{{ $payload ?? __('No request was recorded for this call. Older calls cannot be reconstructed.') }}</textarea></label>
        <label><span class="payload-heading">{{ __('Returned by the LLM') }}<button type="button" data-copy-payload="llm-response">{{ __('Copy JSON') }}</button></span><textarea id="llm-response" readonly spellcheck="false" wrap="soft">{{ $response ?? __('No response was recorded for this call.') }}</textarea></label>
    </div>
</main>
@endsection
