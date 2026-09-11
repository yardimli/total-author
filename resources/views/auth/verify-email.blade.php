@extends('layouts.writer')
@section('content')
<main class="settings-page"><section class="panel"><h1>{{ __('Check your inbox.') }}</h1><p>{{ __('Use the verification link in your email to confirm your address.') }}</p><form method="post" action="{{ route('verification.send') }}">@csrf<button class="primary">{{ __('Resend verification email') }}</button></form></section></main>
@endsection
