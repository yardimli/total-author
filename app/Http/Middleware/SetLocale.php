<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $default = config('app.default_locale', 'en');
        $default = in_array($default, ['en', 'tr'], true) ? $default : 'en';
        $locale = config('app.allow_language_change', false)
            ? ($request->user()?->locale ?? $request->session()->get('locale', $request->cookie('locale', $default)))
            : $default;
        app()->setLocale(in_array($locale, ['en', 'tr'], true) ? $locale : $default);
        return $next($request);
    }
}
