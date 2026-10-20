<?php

namespace App\Console\Commands;

use App\Services\OpenAiConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckOpenAi extends Command
{
    protected $signature = 'kitchen-hq:check-openai';
    protected $description = 'Check the configured OpenAI key and model without printing secrets (small API request)';

    public function handle(): int
    {
        if (! OpenAiConnection::key()) {
            $this->error('OPENAI_API_KEY is missing. Add it to this server .env, then run php artisan config:clear.');
            return self::FAILURE;
        }
        try {
            $response = Http::withToken(OpenAiConnection::key())->connectTimeout(10)->timeout(45)->post('https://api.openai.com/v1/responses', ['model' => OpenAiConnection::model(), 'store' => false, 'max_output_tokens' => 32, 'input' => 'Reply with OK. Connection check only.']);
            if (! $response->successful()) {
                $this->error(OpenAiConnection::error($response));
                return self::FAILURE;
            }
            if ($response->json('status') === 'failed' || ! is_array($response->json('output'))) {
                $this->error('OpenAI returned an unexpected response. Retry the check.');
                return self::FAILURE;
            }
            $this->info('OpenAI Responses API connection passed. Model: '.OpenAiConnection::model().'. No key displayed.');
            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Cannot reach OpenAI. Check server DNS, outbound HTTPS, PHP cURL/CA certificates and timeouts. Certificate verification remains enabled.');
            return self::FAILURE;
        }
    }
}
