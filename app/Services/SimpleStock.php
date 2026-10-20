<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Support\Food;

class SimpleStock
{
    public const LABELS = ['plenty' => 'Plenty', 'low' => 'Nearly out', 'out' => 'Out'];

    public static function level(InventoryItem $item): ?string
    {
        if ($item->quantity !== null) {
            return $item->quantity <= 0 ? 'out' : ($item->quantity <= $item->minimum_quantity ? 'low' : 'plenty');
        }
        return array_key_exists($item->qualitative_stock ?? '', self::LABELS) ? $item->qualitative_stock : null;
    }

    public static function key(string $name): string
    {
        $name = Food::name($name);
        return match ($name) {
            'garden peas', 'peas' => 'peas',
            'frozen cod fillets', 'cod', 'cod fillets' => 'cod',
            'chicken breast fillets', 'chicken breast fillets (fresh)', 'chicken' => 'chicken',
            'sliced cabbage', 'cabbage' => 'cabbage',
            'sliced red onions', 'red onion', 'red onions' => 'red onion',
            'regular potatoes', 'potatoes' => 'potatoes',
            'frozen chips / fries', 'chips', 'fries' => 'chips',
            'hash browns / potato waffles', 'waffles' => 'waffles',
            'chicken pies', 'chicken pie' => 'chicken pie',
            'pork sausages', 'sausages' => 'sausages',
            'chicken goujons / quarter pounders', 'goujons', 'chicken goujons' => 'goujons',
            'chicken dippers / nuggets', 'dippers' => 'dippers',
            'dry basmati / other rice', 'basmati rice', 'rice' => 'rice',
            'naan breads / flatbreads', 'naan', 'naans' => 'naan',
            'macaroni / short pasta', 'short pasta', 'pasta' => 'pasta',
            'cheese / grated cheese', 'cheese', 'grated cheese' => 'cheese',
            'yoghurt / desserts', 'corner yoghurts' => 'corner yoghurt',
            'banana', 'bananas' => 'bananas',
            'tomato', 'tomatoes' => 'tomatoes',
            'stock cubes / stock products', 'stock cubes' => 'stock cubes',
            default => $name,
        };
    }

    public static function assess($inventory, string $name): array
    {
        $matches = $inventory->filter(fn ($i) => self::key($i->name) === self::key($name));
        $levels = $matches->map(fn ($i) => self::level($i));
        if ($levels->contains('plenty')) {
            return ['action' => 'have', 'reason' => 'Plenty in stock'];
        }
        if ($levels->contains('low')) {
            return ['action' => 'buy', 'reason' => 'Nearly out'];
        }
        if ($matches->isNotEmpty() && $levels->every(fn ($level) => $level === 'out')) {
            return ['action' => 'buy', 'reason' => 'Out'];
        }
        return ['action' => 'check', 'reason' => $matches->isEmpty() ? 'Not listed in stock — check before buying' : 'Please check how much is left'];
    }
}
