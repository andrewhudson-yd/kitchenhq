<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TodaySetup;
use Illuminate\Console\Command;

class ApplyDailyKitchen extends Command
{
    protected $signature = 'kitchen-hq:apply-week {email}';
    protected $description = 'Apply the confirmed 20–26 October plan and organise imported household stock';

    public function handle(TodaySetup $setup): int
    {
        $user = User::where('email', strtolower($this->argument('email')))->first();
        if (! $user?->household) {
            $this->error('Create or import this account first.');
            return self::FAILURE;
        }
        $setup->apply($user->household);
        $this->info('Household organised. Confirmed meals, lunches and shopping staples are connected. Repeat runs preserve later edits.');
        return self::SUCCESS;
    }
}
