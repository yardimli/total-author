@if(request()->routeIs('home', 'privacy', 'terms'))
<meta name="robots" content="index, follow">
<link rel="canonical" href="{{ rtrim(config('app.url'), '/').(request()->routeIs('home') ? '/' : '/'.request()->path()) }}">
@else
<meta name="robots" content="noindex, nofollow">
@endif
