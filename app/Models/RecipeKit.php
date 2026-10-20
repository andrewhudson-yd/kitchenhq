<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipeKit extends Model
{
    protected $fillable = ['household_id', 'name', 'brand', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }
}
