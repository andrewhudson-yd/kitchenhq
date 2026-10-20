<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = ['household_id', 'inventory_item_id', 'planned_meal_id', 'change', 'reason'];

    protected function casts(): array
    {
        return ['change' => 'decimal:3'];
    }
}
