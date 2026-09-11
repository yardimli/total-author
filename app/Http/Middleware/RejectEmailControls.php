<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RejectEmailControls
{
    public function handle(Request $request, Closure $next)
    {
        // Reject raw control characters before TrimStrings. Mitigates the Laravel 10 email-rule advisory.
        if (is_string($request->input('email')) && preg_match('/[\x00-\x1F\x7F]/', $request->input('email'))) {
            throw ValidationException::withMessages(['email' => 'Enter an email address without control characters.']);
        }

        return $next($request);
    }
}
