<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipeIngredient extends Model
{
    protected $fillable = ['recipe_id', 'name', 'quantity', 'unit'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }
}
