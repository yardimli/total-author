<footer class="public-footer" id="contact">
<div class="footer-intro"><a class="footer-brand" href="{{ route('home') }}">{{ config('app.name') }}</a><p>{{ __('A place for your words.') }}</p><nav aria-label="{{ __('Footer navigation') }}"><a href="{{ route('privacy') }}">{{ __('Privacy') }}</a><a href="{{ route('terms') }}">{{ __('Terms') }}</a><a href="#contact-form">{{ __('Contact') }}</a></nav><small>© {{ date('Y') }} {{ config('app.name') }}</small></div>
<div class="contact-section"><h2>{{ __('Get in touch') }}</h2><p>{{ __('Questions, feedback, or a little help getting started.') }}</p>
@if(session('contact_status'))<p role="status">{{ session('contact_status') }}</p>@endif
@if($errors->contact->any())<div role="alert">@foreach($errors->contact->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form id="contact-form" action="{{ route('contact.store') }}" method="post">@csrf
<div class="contact-pair"><label>{{ __('Name') }}<input name="name" autocomplete="name" value="{{ old('name') }}" required maxlength="200"></label><label>{{ __('Email') }}<input name="email" type="email" autocomplete="email" value="{{ old('email') }}" required maxlength="255"></label></div>
<label>{{ __('Subject') }}<input name="subject" value="{{ old('subject') }}" required maxlength="200"></label><label>{{ __('Message') }}<textarea name="message" rows="3" required maxlength="10000">{{ old('message') }}</textarea></label><button class="contact-submit" type="submit">{{ __('Send message ↗') }}</button><small>{{ __('Your message is stored for our team. No automatic email is sent.') }}</small>
</form></div>
</footer>