<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FamilyProfile extends Model
{
    protected $fillable = ['household_id', 'name', 'age', 'likes', 'dislikes', 'notes'];

    protected function casts(): array
    {
        return [];
    }
}
