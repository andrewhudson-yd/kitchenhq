<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = ['household_id', 'storage_location_id', 'name', 'quantity', 'unit', 'minimum_quantity', 'expires_at', 'notes'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'minimum_quantity' => 'decimal:3', 'expires_at' => 'date'];
    }

    public function location()
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id');
    }

    public function setExpiresAtAttribute($value): void
    {
        $this->attributes['expires_at'] = $value ? CarbonImmutable::parse($value)->toDateString() : null;
    }

    public function getStockLevelAttribute(): string
    {
        if ($this->quantity === null) {
            return 'Unverified';
        }
        if ($this->quantity <= 0) {
            return 'Out of stock';
        }
        if ($this->quantity <= $this->minimum_quantity) {
            return 'Low stock';
        }

        return 'In stock';
    }
}
