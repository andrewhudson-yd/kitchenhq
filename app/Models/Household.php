<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Household extends Model
{
    protected $fillable = ['name', 'join_code', 'plan_starts_on', 'ai_api_key', 'ai_model'];

    protected $hidden = ['ai_api_key', 'join_code'];

    protected function casts(): array
    {
        return ['ai_api_key' => 'encrypted'];
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
