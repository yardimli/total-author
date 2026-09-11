@extends('layouts.writer')
@section('content')
<main class="auth-layout"><section class="auth-story"><div class="eyebrow">{{ mb_strtoupper(config('app.name')) }}</div><h1>{{ __('A quiet place.') }}<br>{{ __('A world of') }}<br><em>{{ __('possibilities.') }}</em></h1><div class="auth-rule"></div><p>{{ __('For the characters who won’t leave you.') }}<br>{{ __('For the places you haven’t been.') }}<br>{{ __('For the story only you can tell.') }}</p><span class="ornament">❧</span></section>
<section class="auth-card"><div class="eyebrow">{{ __('THE NEXT CHAPTER') }}</div><h2>{{ $heading }}</h2><p class="muted">{{ $intro }}</p>
<form method="post" action="{{ route($action) }}">@csrf
@if ($kind === 'register')<label for="name">{{ __('Your name') }}</label><input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus>@endif
@if ($kind !== 'confirm')<label for="email">{{ __('Email address') }}</label><input id="email" type="email" name="email" value="{{ old('email', $request->email ?? '') }}" autocomplete="username" required>@endif
@if ($kind === 'reset')<input type="hidden" name="token" value="{{ $request->route('token') }}">@endif
@if ($kind !== 'forgot')<label for="password">{{ __('Password') }}</label><input id="password" type="password" name="password" autocomplete="{{ in_array($kind,['register','reset']) ? 'new-password' : 'current-password' }}" required>@endif
@if (in_array($kind,['register','reset']))<label for="password_confirmation">{{ __('Confirm password') }}</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>@endif
@if ($kind === 'login')<label class="check"><input type="checkbox" name="remember"> {{ __('Keep me signed in') }}</label>@endif
<button class="primary">{{ $button }} ↗</button></form>
@if(in_array($kind, ['login', 'register']) && \App\Support\Integrations::google())<a class="google-login" href="{{ route('login.google') }}">{{ __('Continue with Google') }}</a>@endif
<div class="auth-footer">@if ($kind === 'login')@if(\App\Support\Integrations::mail())<a href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>@endif<p>{{ __('New here?') }} <a href="{{ route('register') }}">{{ __('Create your account') }}</a></p>@else<a href="{{ route('login') }}">{{ __('← Back to sign in') }}</a>@endif</div>
</section></main>
@include('partials.public-footer')
@endsection
