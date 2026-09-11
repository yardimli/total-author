<?php

namespace App\Services;

use App\Models\AiCall;
use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class OpenRouter
{
    public function model(string $id): array
    {
        $model = collect(app(ModelCatalog::class)->get()['data'])->firstWhere('id', $id);
        if (! $model) {
            throw ValidationException::withMessages(['model' => __('Select an available model. Refresh the catalog if needed.')]);
        }
        $outputs = $model['architecture']['output_modalities'] ?? [];
        if (stripos(($model['name'] ?? '').' '.$model['id'], 'batch') !== false || ! in_array('text', $outputs) || in_array('image', $outputs)) {
            throw ValidationException::withMessages(['model' => __('Choose a text-output model without batch or image generation.')]);
        }

        return $model;
    }

    public function reserve(User $user, Book $book, array $model, array $messages, string $stage): AiCall
    {
        $output = config('writer.max_output_tokens');
        // UTF-8 byte length is a conservative upper estimate for text tokens. Include framing overhead.
        $input = strlen(json_encode($messages, JSON_UNESCAPED_UNICODE)) + count($messages) * 64 + 1024;
        abort_if($input + $output > ($model['context_length'] ?? 0), 422, __('This request may exceed the model context. Reduce history, select a larger model, or explicitly narrow the document scope. Nothing was sent.'));
        $pricing = app(ModelPricing::class)->ceilings($model['pricing'] ?? []);
        // Text-only requests never enable search or send image/audio/video inputs.
        // Cache and reasoning allowances deliberately overestimate their token subsets.
        $inputRate = $pricing['prompt'] + max($pricing['input_cache_read'], $pricing['input_cache_write']);
        $outputRate = $pricing['completion'] + $pricing['internal_reasoning'];
        $reserve = ceil(($input * $inputRate + $output * $outputRate + $pricing['request']) * 100000000) / 100000000;

        return DB::transaction(function () use ($user, $book, $model, $stage, $reserve) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $demo = ! $user->openrouter_key;
            abort_if($demo && ! config('writer.openrouter_key'), 422, __('Add your OpenRouter API key in Account settings. The demo key is not configured.'));
            abort_if($demo && (float) $user->demo_spent + (float) $user->demo_reserved + $reserve > config('writer.demo_limit'), 422, __('This request exceeds your remaining $1 demo allowance. Choose a lower-cost model or add your own key.'));
            if ($demo) {
                $user->demo_reserved = (float) $user->demo_reserved + $reserve;
                $user->save();
            }

            return AiCall::create(['user_id' => $user->id, 'book_id' => $book->id, 'model' => $model['id'], 'stage' => $stage, 'funding' => $demo ? 'demo' : 'personal', 'reserved' => $reserve]);
        });
    }

    public function release(AiCall $call): void
    {
        DB::transaction(function () use ($call) {
            $call = AiCall::lockForUpdate()->findOrFail($call->id);
            if ($call->status !== 'reserved') {
                return;
            }
            $user = User::lockForUpdate()->findOrFail($call->user_id);
            if ($call->funding === 'demo') {
                $user->demo_reserved = max(0, (float) $user->demo_reserved - (float) $call->reserved);
                $user->save();
            }
            $call->update(['status' => 'cancelled', 'cost' => 0]);
        });
    }

    public function send(User $user, AiCall $call, array $model, array $messages): array
    {
        $pricing = app(ModelPricing::class)->ceilings($model['pricing'] ?? []);
        $call->update(['status' => 'pending']);
        try {
            $payload = [
                'model' => $model['id'], 'messages' => $messages, 'max_tokens' => config('writer.max_output_tokens'),
                'provider' => ['allow_fallbacks' => false, 'require_parameters' => true, 'max_price' => ['prompt' => $pricing['prompt'] * 1000000, 'completion' => $pricing['completion'] * 1000000]],
                'plugins' => [],
                'transforms' => [],
                'usage' => ['include' => true],
            ];
            $call->update(['request_payload' => $payload]);
            $response = Http::withToken($user->openrouter_key ?: config('writer.openrouter_key'))->connectTimeout(15)->timeout(120)->post(config('writer.openrouter_url').'/chat/completions', $payload);
            $call->update(['response_body' => $response->body(), 'response_status' => $response->status()]);
            $body = $response->json();
            $tokens = [];
            foreach (['prompt_tokens', 'completion_tokens', 'total_tokens'] as $key) {
                $value = $body['usage'][$key] ?? null;
                if (is_numeric($value) && $value >= 0) {
                    $tokens[$key] = (int) $value;
                }
            }
            if ($tokens) {
                $call->update($tokens);
            }
            if (isset($body['id'])) {
                $call->update(['provider_id' => $body['id']]);
            }
            $cost = $body['usage']['cost'] ?? null;
            if (is_numeric($cost) && $cost >= 0) {
                $this->settle($call, (float) $cost);
            }
            if (! $response->successful() || isset($body['error'])) {
                throw new \RuntimeException(__('Provider failed'));
            }
            $text = $body['choices'][0]['message']['content'] ?? '';
            $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($text));
            $json = json_decode($text, true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($json)) {
                throw new \RuntimeException(__('Invalid JSON'));
            }

            return $json;
        } catch (\Throwable $e) {
            $call->update(['error' => isset($response) ? __('Provider error or invalid response JSON.') : __('Request failed before a response was received.')]);
            // Unknown billing outcomes keep their reservation. Never refund an uncertain paid request.
            throw ValidationException::withMessages(['ai' => $e instanceof \Illuminate\Http\Client\ConnectionException
                ? __('The AI connection timed out or was interrupted. You can send another message without refreshing. Any uncertain provider cost remains reserved.')
                : __('The AI request failed or returned invalid JSON. No changes were applied. Any uncertain cost remains reserved for reconciliation.')]);
        }
    }

    public function settle(AiCall $call, float $cost): void
    {
        DB::transaction(function () use ($call, $cost) {
            $locked = AiCall::lockForUpdate()->findOrFail($call->id);
            if ($locked->cost !== null) {
                return;
            }
            $user = User::lockForUpdate()->findOrFail($locked->user_id);
            if ($locked->funding === 'demo') {
                $user->demo_reserved = max(0, (float) $user->demo_reserved - (float) $locked->reserved);
                $user->demo_spent = (float) $user->demo_spent + $cost;
                $user->save();
            }
            $locked->update(['status' => 'settled', 'cost' => $cost]);
        });
    }

    public function reconcile(AiCall $call): bool
    {
        if (! $call->provider_id || $call->cost !== null) {
            return false;
        }
        $user = User::findOrFail($call->user_id);
        $key = $call->funding === 'demo' ? config('writer.openrouter_key') : $user->openrouter_key;
        if (! $key) {
            return false;
        }
        try {
            $cost = Http::withToken($key)->timeout(20)->get(config('writer.openrouter_url').'/generation', ['id' => $call->provider_id])->throw()->json('data.total_cost');
            if (! is_numeric($cost) || $cost < 0) {
                return false;
            }
            $this->settle($call, (float) $cost);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
