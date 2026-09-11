<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class LanguageController extends Controller
{
    public function update(Request $request)
    {
        abort_unless(config('app.allow_language_change', false), 403);
        $data = $request->validate(['locale' => 'required|in:en,tr']);
        $request->session()->put('locale', $data['locale']);
        if ($user = $request->user()) {
            $user->locale = $data['locale'];
            $user->save();
        }
        return back()->withCookie(cookie('locale', $data['locale'], 525600));
    }
}
