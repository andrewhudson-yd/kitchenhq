<?php

namespace App\Services;

use App\Models\Household;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CookMeal
{
    public function handle(Household $h, int $id): void
    {
        DB::transaction(function () use ($h, $id) {
            // A household lock serializes cooking and manual stock changes on MariaDB.
            Household::whereKey($h->id)->lockForUpdate()->firstOrFail();
            $meal = $h->meals()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($meal->cooked_at) {
                return;
            }
            $recipe = $meal->recipe()->with('ingredients', 'kit')->firstOrFail();
            if ($recipe->planning_only || $recipe->ingredients->isEmpty()) {
                $this->fail('Finish this recipe’s ingredients and mark it ready before cooking.');
            }
            if ($recipe->is_simplycook && ! $recipe->kit) {
                $this->fail('Link the SimplyCook kit before cooking this meal.');
            }
            $needs = [];
            foreach ($recipe->ingredients as $i) {
                $key = $i->name.'|'.$i->unit;
                $needs[$key] ??= ['name' => $i->name, 'unit' => $i->unit, 'quantity' => 0];
                $needs[$key]['quantity'] += (float) $i->quantity * $meal->servings / $recipe->servings;
            }
            foreach ($needs as $need) {
                $remaining = round($need['quantity'], 3);
                $stock = $h->inventory()->where('name', $need['name'])->where('unit', $need['unit'])->whereNotNull('quantity')->where('quantity', '>', 0)
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', today()->toDateString()))
                    ->orderByRaw('expires_at IS NULL')->orderBy('expires_at')->orderBy('id')->lockForUpdate()->get();
                if ((float) $stock->sum('quantity') + 0.00001 < $remaining) {
                    $this->fail('Insufficient verified stock: '.$need['name'].' ('.$need['unit'].'). Verify or add stock first.');
                }
                foreach ($stock as $item) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $used = min((float) $item->quantity, $remaining);
                    $item->update(['quantity' => round((float) $item->quantity - $used, 3)]);
                    StockMovement::create(['household_id' => $h->id, 'inventory_item_id' => $item->id, 'planned_meal_id' => $meal->id, 'change' => -$used, 'reason' => 'Cooked: '.$recipe->name]);
                    $remaining = round($remaining - $used, 3);
                }
            }
            if ($recipe->kit) {
                $kit = $h->kits()->whereKey($recipe->kit->id)->lockForUpdate()->firstOrFail();
                $used = (int) ceil($meal->servings / $recipe->servings);
                if ($kit->quantity === null || $kit->quantity < $used) {
                    $this->fail('Insufficient verified recipe kits. Count your kits first.');
                }
                $kit->update(['quantity' => $kit->quantity - $used]);
            }
            $meal->update(['cooked_at' => now()]);
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['stock' => $message]);
    }
}
