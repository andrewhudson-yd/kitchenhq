<?php

namespace App\Services;

use App\Models\Household;
use Carbon\CarbonImmutable;

class ShoppingList
{
    public function forWeek(Household $h, CarbonImmutable $week): array
    {
        $meals = $h->meals()->with('recipe.ingredients', 'recipe.kit')->whereNull('cooked_at')->whereBetween('date', [$week->toDateString(), $week->addDays(6)->toDateString()])->get();
        $needs = [];
        $incomplete = [];
        $kits = [];
        foreach ($meals as $meal) {
            $recipe = $meal->recipe;
            if ($recipe->planning_only || $recipe->ingredients->isEmpty() || ($recipe->is_simplycook && ! $recipe->kit)) {
                $incomplete[] = $recipe->name;
            }
            foreach ($recipe->ingredients as $ingredient) {
                $key = $ingredient->name.'|'.$ingredient->unit;
                $needs[$key] ??= ['name' => $ingredient->name, 'unit' => $ingredient->unit, 'required' => 0];
                $needs[$key]['required'] += (float) $ingredient->quantity * $meal->servings / $recipe->servings;
            }
            if ($recipe->kit) {
                $kit = $recipe->kit;
                $kits[$kit->id] ??= ['name' => $kit->brand.' / '.$kit->name, 'unit' => 'kit', 'required' => 0, 'stock' => $kit->quantity ?? 0, 'unknown' => $kit->quantity === null];
                $kits[$kit->id]['required'] += (int) ceil($meal->servings / $recipe->servings);
            }
        }
        $inventory = $h->inventory()->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', today()->toDateString()))->get();
        $rows = [];
        foreach ($needs as $need) {
            $matching = $inventory->filter(fn ($i) => $i->name === $need['name'] && $i->unit === $need['unit']);
            $stock = (float) $matching->sum('quantity');
            $rows[] = $need + ['stock' => $stock, 'unknown' => $matching->contains(fn ($i) => $i->quantity === null), 'buy' => max(0, round($need['required'] - $stock, 3))];
        }
        foreach ($kits as $kit) {
            $rows[] = $kit + ['buy' => max(0, $kit['required'] - $kit['stock'])];
        }
        usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return ['rows' => $rows, 'incomplete' => array_values(array_unique($incomplete))];
    }
}
