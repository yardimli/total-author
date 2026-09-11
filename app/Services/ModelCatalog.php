<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ModelCatalog
{
    public function get(): array
    {
        return Cache::get('openrouter.catalog', ['data' => [], 'refreshed_at' => null]);
    }

    public function refresh(): array
    {
        try {
            $models = Http::timeout(15)->get(config('writer.openrouter_url').'/models')->throw()->json('data');
            if (! is_array($models)) {
                throw new \RuntimeException(__('Invalid catalog'));
            }
            $catalog = ['data' => $models, 'refreshed_at' => now()->toIso8601String()];
            Cache::forever('openrouter.catalog', $catalog);

            return $catalog;
        } catch (\Throwable $e) {
            return $this->get() + ['error' => __('The model catalog could not refresh. Showing the last successful catalog.')];
        }
    }
}
