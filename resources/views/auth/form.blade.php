@extends('layouts.writer')
@section('content')
<main class="auth-layout"><section class="auth-story"><div class="eyebrow">{{ mb_strtoupper(config('app.name')) }}</div><h1>A quiet place.<br>A world of<br><em>possibilities.</em></h1><div class="auth-rule"></div><p>For the characters who won’t leave you.<br>For the places you haven’t been.<br>For the story only you can tell.</p><span class="ornament">❧</span></section>
<section class="auth-card"><div class="eyebrow">THE NEXT CHAPTER</div><h2>{{ $heading }}</h2><p class="muted">{{ $intro }}</p>
<form method="post" action="{{ route($action) }}">@csrf
@if ($kind === 'register')<label for="name">Your name</label><input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus>@endif
@if ($kind !== 'confirm')<label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email', $request->email ?? '') }}" autocomplete="username" required>@endif
@if ($kind === 'reset')<input type="hidden" name="token" value="{{ $request->route('token') }}">@endif
@if ($kind !== 'forgot')<label for="password">Password</label><input id="password" type="password" name="password" autocomplete="{{ in_array($kind,['register','reset']) ? 'new-password' : 'current-password' }}" required>@endif
@if (in_array($kind,['register','reset']))<label for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>@endif
@if ($kind === 'login')<label class="check"><input type="checkbox" name="remember"> Keep me signed in</label>@endif
<button class="primary">{{ $button }} ↗</button></form>
@if(in_array($kind, ['login', 'register']) && \App\Support\Integrations::google())<a class="google-login" href="{{ route('login.google') }}">Continue with Google</a>@endif
<div class="auth-footer">@if ($kind === 'login')@if(\App\Support\Integrations::mail())<a href="{{ route('password.request') }}">Forgot your password?</a>@endif<p>New here? <a href="{{ route('register') }}">Create your account</a></p>@else<a href="{{ route('login') }}">← Back to sign in</a>@endif</div>
</section></main>
@include('partials.public-footer')
@endsection
