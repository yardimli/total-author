@extends('layouts.writer')
@section('title', 'Account · '.config('app.name'))
@section('content')
<main class="settings-page"><div class="eyebrow">YOUR WRITING PRACTICE</div><h1>Account & AI allowance</h1>
<div class="settings-grid"><section class="panel"><h2>Your OpenRouter key</h2><p class="muted">Use your own key for continued AI assistance. It is encrypted at rest and never returned to your browser.</p>
<p>{{ auth()->user()->openrouter_key ? 'A personal key is saved.' : 'Using the demo allowance when available.' }}</p>
<form method="post" action="{{ route('settings.update') }}">@csrf @method('PATCH')<label for="api-key">Personal API key</label><input id="api-key" type="password" name="openrouter_key" autocomplete="off" placeholder="Enter a key to save or replace" required><button class="primary">Save API key</button></form>
<form method="post" action="{{ route('settings.update') }}">@csrf @method('PATCH')<input type="hidden" name="openrouter_key" value=""><button class="quiet">Remove saved key</button></form></section>
<section class="panel"><h2>Account usage</h2><div class="large-number">${{ \App\Support\Money::display($spent) }}</div><p>Total settled AI usage across your books</p><hr><p>Demo spent: ${{ \App\Support\Money::display(auth()->user()->demo_spent) }}</p><p>Demo reserved: ${{ \App\Support\Money::display(auth()->user()->demo_reserved) }}</p><p>Demo remaining: ${{ \App\Support\Money::display(max(0,1-auth()->user()->demo_spent-auth()->user()->demo_reserved)) }}</p><small>Classification and writing both count. Uncertain provider charges remain reserved until reconciled.</small></section></div>
<form class="account-sign-out" action="{{ route('logout') }}" method="post">@csrf<button type="submit">Sign out</button></form>
</main>
@endsection
