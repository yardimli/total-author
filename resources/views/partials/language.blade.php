@if(config('app.allow_language_change', false))
<form class="language-switch" method="post" action="{{ route('language.update') }}">@csrf
<label class="sr-only" for="language-choice">{{ __('Language') }}</label><select id="language-choice" name="locale" aria-label="{{ __('Language') }}"><option value="en" @selected(app()->getLocale() === 'en')>English</option><option value="tr" @selected(app()->getLocale() === 'tr')>Türkçe</option></select><noscript><button type="submit" aria-label="{{ __('Change language') }}">↵</button></noscript></form>

@endif
