<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DefaultFavorites
{
    public function initialize(Request $request, ModelCatalog $catalog): void
    {
        $user = $request->user();
        if (($user->favorites_initialized_at || ! empty($user->favorite_models)) && $user->model_selected_at) {
            return;
        }
        $models = $catalog->get();
        if ($request->session()->pull('refresh_model_catalog', false) || empty($models['data'])) {
            $models = $catalog->refresh();
        }
        // Leave initialization pending when no catalog is available, so a later visit can retry.
        if (empty($models['data'])) {
            return;
        }
        $available = collect($models['data'])->filter(function ($model) {
            $outputs = $model['architecture']['output_modalities'] ?? [];

            return isset($model['id']) && stripos(($model['name'] ?? '').' '.$model['id'], 'batch') === false && in_array('text', $outputs) && ! in_array('image', $outputs);
        })->pluck('id')->all();
        $favorites = array_values(array_intersect(config('default_favorites', []), $available));
        DB::transaction(function () use ($user, $favorites, $available) {
            $current = User::lockForUpdate()->findOrFail($user->id);
            if (! $current->favorites_initialized_at && empty($current->favorite_models)) {
                $current->favorite_models = $favorites;
                $current->favorites_initialized_at = now();
            }
            if (! $current->model_selected_at) {
                $supportedFavorites = array_values(array_intersect($current->favorite_models ?? [], $available));
                $current->selected_model = in_array('openai/gpt-5.6-sol', $supportedFavorites, true)
                    ? 'openai/gpt-5.6-sol'
                    : ($supportedFavorites[0] ?? null);
            }
            $current->save();
            $user->setRawAttributes($current->getAttributes(), true);
        });
    }
}
