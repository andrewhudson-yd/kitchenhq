<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class Household extends Model
{
    protected $fillable = ['name', 'join_code', 'plan_starts_on', 'ai_api_key', 'ai_model'];

    protected $hidden = ['ai_api_key', 'join_code'];

    protected function casts(): array
    {
        return ['ai_api_key' => 'encrypted'];
    }

    /** The start of the planning week that contains today, following the household's weekly rhythm. */
    public function currentWeek(): CarbonImmutable
    {
        return $this->weekOf(CarbonImmutable::today());
    }

    /** The start of the planning week that contains a given day. */
    public function weekOf(CarbonImmutable $day): CarbonImmutable
    {
        $day = $day->startOfDay();
        if (! $this->plan_starts_on) {
            return $day->startOfWeek();
        }
        $start = CarbonImmutable::parse($this->plan_starts_on);

        return $start->addDays(intdiv((int) $start->diffInDays($day, false) - ($day < $start ? 6 : 0), 7) * 7);
    }

    /** Link parameters for a week's page: none for the current week, so the address stays the same. */
    public function weekParams(string $week): array
    {
        return $week === $this->currentWeek()->toDateString() ? [] : ['week' => $week];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function profiles()
    {
        return $this->hasMany(FamilyProfile::class);
    }

    public function locations()
    {
        return $this->hasMany(StorageLocation::class);
    }

    public function inventory()
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }

    public function kits()
    {
        return $this->hasMany(RecipeKit::class);
    }

    public function meals()
    {
        return $this->hasMany(PlannedMeal::class);
    }
}
