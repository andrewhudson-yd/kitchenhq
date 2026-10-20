<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MoveOpenAiKey extends Command
{
    protected $signature = 'kitchen-hq:move-openai-key {email}';
    protected $description = 'Copy an existing household OpenAI key to the private server .env without displaying it';

    public function handle(): int
    {
        $path = base_path('.env');
        if (! is_file($path) || ! is_writable($path)) {
            $this->error('The server .env is missing or not writable. Use your hosting environment editor.');
            return self::FAILURE;
        }
        $contents = file_get_contents($path);
        if (preg_match('/^OPENAI_API_KEY[ \t]*=[ \t]*([^\r\n]*)/m', $contents, $match) && trim($match[1], " \t\"'") !== '') {
            $this->error('OPENAI_API_KEY already exists in .env. It was not overwritten.');
            return self::FAILURE;
        }
        $key = User::where('email', strtolower($this->argument('email')))->first()?->household?->ai_api_key;
        if (! $key || preg_match('/[\r\n]/', $key)) {
            $this->error('No usable saved household key found. Add OPENAI_API_KEY using your hosting environment editor.');
            return self::FAILURE;
        }
        $line = 'OPENAI_API_KEY="'.str_replace(['\\', '"'], ['\\\\', '\\"'], trim($key)).'"';
        $contents = preg_match('/^OPENAI_API_KEY\s*=/m', $contents) ? preg_replace_callback('/^OPENAI_API_KEY\s*=.*$/m', fn () => $line, $contents) : rtrim($contents)."\n".$line."\n";
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            $this->error('Could not update .env.');
            return self::FAILURE;
        }
        $this->info('Saved to .env without displaying the key. Original database records are preserved. Run config:clear, then kitchen-hq:check-openai.');
        return self::SUCCESS;
    }
}
