<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class PlannedMeal extends Model
{
    protected $fillable = ['household_id', 'recipe_id', 'date', 'slot', 'audience', 'servings', 'cooked_at'];

    protected function casts(): array
    {
        return ['date' => 'date', 'cooked_at' => 'datetime'];
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function setDateAttribute($value): void
    {
        $this->attributes['date'] = CarbonImmutable::parse($value)->toDateString();
    }
}
