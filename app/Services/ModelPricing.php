<?php

namespace App\Services;

class ModelPricing
{
    public function ceilings(array $pricing): array
    {
        $rates = array_fill_keys(['prompt', 'completion', 'request', 'internal_reasoning', 'input_cache_read', 'input_cache_write'], 0.0);
        foreach (['prompt', 'completion'] as $key) {
            abort_unless(isset($pricing[$key]), 422, __('This model does not have bounded pricing.'));
        }
        $overrides = $pricing['overrides'] ?? [];
        abort_unless(is_array($overrides), 422, __('This model does not have bounded pricing.'));
        // Reserve the highest advertised rate, including conditional context/time tiers.
        foreach ([$pricing, ...$overrides] as $tier) {
            abort_unless(is_array($tier), 422, __('This model does not have bounded pricing.'));
            foreach ($tier as $key => $value) {
                if (array_key_exists($key, $rates)) {
                    abort_unless(is_numeric($value) && is_finite((float) $value) && $value >= 0, 422, __('This model does not have bounded pricing.'));
                    $rates[$key] = max($rates[$key], (float) $value);
                } elseif (! in_array($key, ['overrides', 'min_prompt_tokens', 'utc_start', 'utc_end', 'utc_days', 'web_search', 'image', 'audio', 'input_audio', 'output_audio', 'video'])) {
                    abort_unless(is_numeric($value) && (float) $value === 0.0, 422, __('This model has an unsupported pricing fee. Choose another model.'));
                }
            }
        }

        return $rates;
    }
}
