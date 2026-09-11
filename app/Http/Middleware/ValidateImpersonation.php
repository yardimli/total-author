<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ValidateImpersonation
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->session()->has('impersonator_id') && ! User::whereKey($request->session()->get('impersonator_id'))->where('is_admin', true)->exists()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
