<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Integrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleLoginController extends Controller
{
    public function redirect()
    {
        abort_unless(Integrations::google(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        abort_unless(Integrations::google(), 404);
        try {
            $profile = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')->withErrors(['google' => 'Google sign-in was cancelled or expired. Please try again.']);
        }
        $email = Str::lower($profile->getEmail() ?? '');
        if (! $profile->getId() || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! ($profile->user['email_verified'] ?? $profile->user['verified_email'] ?? false)) {
            return redirect()->route('login')->withErrors(['google' => 'Google must provide a verified email address.']);
        }
        $user = User::where('google_id', $profile->getId())->first();
        if (! $user) {
            if (User::where('email', $email)->exists()) {
                return redirect()->route('login')->withErrors(['google' => 'An account already uses this email. Please sign in with its password.']);
            }
            $user = new User(['name' => Str::limit($profile->getName() ?: $email, 255, ''), 'email' => $email, 'password' => Hash::make(Str::random(64))]);
            $user->google_id = $profile->getId();
            $user->email_verified_at = now();
            $user->theme = 'paper';
            $user->save();
        }
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
