<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class CaptureEmailControls
{
    public function handle(Request $request, Closure $next)
    {
        // Preserve the raw validation result before TrimStrings removes control characters.
        $request->attributes->set('invalid_email_controls', is_string($request->input('email')) && preg_match('/[\x00-\x1F\x7F]/', $request->input('email')));
        return $next($request);
    }
}
