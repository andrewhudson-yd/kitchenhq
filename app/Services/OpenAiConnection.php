<?php

namespace App\Services;

use Illuminate\Http\Client\Response;

class OpenAiConnection
{
    public static function key(): string
    {
        return trim((string) config('services.openai.key', ''));
    }

    public static function model(): string
    {
        return (string) config('services.openai.model', 'gpt-4.1-mini');
    }

    public static function error(Response $response): string
    {
        $code = (string) ($response->json('error.code') ?? $response->json('error.type') ?? '');
        $message = match (true) {
            $code === 'insufficient_quota', in_array($code, ['credit_balance_exhausted', 'billing_hard_limit_reached'], true) => 'OpenAI API credits or spending allowance are exhausted. Check API billing; a ChatGPT subscription does not supply API credits.',
            $response->status() === 401, $code === 'invalid_api_key' => 'OpenAI rejected OPENAI_API_KEY. Check the key in the server .env and rebuild the configuration cache.',
            $code === 'model_not_found', $response->status() === 404 => 'OPENAI_MODEL is unavailable to this API project. Check the model name and project permissions.',
            $response->status() === 403 => 'OpenAI denied access. Check API project permissions and any regional or network restrictions.',
            $response->status() === 429 => 'OpenAI rate limit reached. Wait a moment and try again.',
            $response->status() === 400 => 'OpenAI rejected the request. Check the model supports image input and the Responses API. Try a clear JPG or PNG.',
            $response->status() >= 500 => 'OpenAI is temporarily unavailable. Please try again later.',
            default => 'OpenAI request failed. Run php artisan kitchen-hq:check-openai on the server.',
        };
        $safeCode = preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $code) ? $code : 'unknown';
        return $message.' (HTTP '.$response->status().'; '.$safeCode.')';
    }
}
