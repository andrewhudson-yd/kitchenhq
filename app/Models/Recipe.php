<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    protected $fillable = ['household_id', 'recipe_kit_id', 'name', 'servings', 'is_simplycook', 'is_roast', 'planning_only', 'instructions', 'shopping_ingredients'];

    protected function casts(): array
    {
        return ['is_simplycook' => 'boolean', 'is_roast' => 'boolean', 'planning_only' => 'boolean', 'shopping_ingredients' => 'array'];
    }

    public function ingredients()
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function kit()
    {
        return $this->belongsTo(RecipeKit::class, 'recipe_kit_id');
    }
}
