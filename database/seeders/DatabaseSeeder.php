<?php

namespace Database\Seeders;

use App\Models\Household;
use App\Services\HouseholdSetup;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // No fixed-password accounts or invented stock. Register to create the first household.
        Household::all()->each(fn ($h) => app(HouseholdSetup::class)->seed($h));
    }
}
