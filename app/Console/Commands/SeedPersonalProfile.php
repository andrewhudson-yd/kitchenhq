<?php

namespace App\Console\Commands;

use App\Services\PersonalHouseholdImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedPersonalProfile extends Command
{
    protected $signature = 'kitchen-hq:seed-profile {email}';
    protected $description = 'Import the supplied personal household dataset; prompt privately for a password';

    public function handle(PersonalHouseholdImport $import): int
    {
        $email = $this->argument('email');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid email is required.');
            return self::FAILURE;
        }
        $password = $this->secret('Account password (minimum 10 characters)');
        if (! is_string($password) || strlen($password) < 10) {
            $this->error('Password must have at least 10 characters.');
            return self::FAILURE;
        }
        $user = $import->run($email, Hash::make($password));
        $this->info('Profile imported into household '.$user->household_id.'. Unknown amounts remain unverified.');
        return self::SUCCESS;
    }
}
