@extends('layouts.writer')
@section('title', __('Account · ').config('app.name'))
@section('content')
<main class="settings-page"><div class="eyebrow">{{ __('YOUR WRITING PRACTICE') }}</div><h1>{{ __('Account & AI allowance') }}</h1>
<div class="settings-grid"><section class="panel"><h2>{{ __('Your OpenRouter key') }}</h2><p class="muted">{{ __('Use your own key for continued AI assistance. It is encrypted at rest and never returned to your browser.') }}</p>
<p>{{ auth()->user()->openrouter_key ? __('A personal key is saved.') : __('Using the demo allowance when available.') }}</p>
<form method="post" action="{{ route('settings.update') }}">@csrf @method('PATCH')<label for="api-key">{{ __('Personal API key') }}</label><input id="api-key" type="password" name="openrouter_key" autocomplete="off" placeholder="{{ __('Enter a key to save or replace') }}" required><button class="primary">{{ __('Save API key') }}</button></form>
<form method="post" action="{{ route('settings.update') }}">@csrf @method('PATCH')<input type="hidden" name="openrouter_key" value=""><button class="quiet">{{ __('Remove saved key') }}</button></form></section>
<section class="panel"><h2>{{ __('Account usage') }}</h2><div class="large-number">${{ \App\Support\Money::display($spent) }}</div><p>{{ __('Total settled AI usage across your books') }}</p><hr><p>{{ __('Demo spent: $') }}{{ \App\Support\Money::display(auth()->user()->demo_spent) }}</p><p>{{ __('Demo reserved: $') }}{{ \App\Support\Money::display(auth()->user()->demo_reserved) }}</p><p>{{ __('Demo remaining: $') }}{{ \App\Support\Money::display(max(0,1-auth()->user()->demo_spent-auth()->user()->demo_reserved)) }}</p><small>{{ __('Classification and writing both count. Uncertain provider charges remain reserved until reconciled.') }}</small></section></div>
<form class="account-sign-out" action="{{ route('logout') }}" method="post">@csrf<button type="submit">{{ __('Sign out') }}</button></form>
</main>
@endsection
