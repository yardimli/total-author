<?php

namespace App\Http\Controllers;

use App\Models\AiCall;
use App\Services\ModelCatalog;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit(Request $request)
    {
        return view('settings', ['spent' => AiCall::where('user_id', $request->user()->id)->sum('cost')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['openrouter_key' => 'nullable|string|max:500', 'selected_model' => 'nullable|string|max:200', 'favorite_models' => 'sometimes|array|max:200',
            'favorite_models.*' => 'string|max:200', 'favorites_only' => 'sometimes|boolean', 'theme' => 'sometimes|in:paper,light,dark']);
        $user = $request->user();
        foreach ($data as $key => $value) {
            $user->$key = $value;
        }
        if (array_key_exists('favorite_models', $data)) {
            $user->favorites_initialized_at = now();
        }
        if (array_key_exists('selected_model', $data)) {
            $user->model_selected_at = now();
        }
        $user->save();

        return $request->expectsJson() ? response()->json(['saved' => true]) : back()->with('status', __('Settings saved.'));
    }

    public function models(Request $request, ModelCatalog $catalog)
    {
        // The login event requests one refresh. Reading the picker never schedules another.
        if ($request->session()->pull('refresh_model_catalog', false)) {
            return $catalog->refresh();
        }

        return $catalog->get();
    }

    public function refresh(ModelCatalog $catalog)
    {
        return $catalog->refresh();
    }
}
