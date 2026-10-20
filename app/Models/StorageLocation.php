<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorageLocation extends Model
{
    protected $fillable = ['household_id', 'name', 'type', 'verified_at'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function items()
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function isConfirmedEmpty(): bool
    {
        return $this->type === 'freezer' && $this->verified_at !== null
            && ! $this->items()->where(fn ($q) => $q->whereNull('quantity')->orWhere('quantity', '>', 0))->exists();
    }
}
