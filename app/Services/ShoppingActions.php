<?php

namespace App\Services;

use App\Models\Household;
use App\Support\FoodDictionary;
use App\Support\ShoppingQuantity;
use Illuminate\Support\Facades\DB;

/**
 * The shopping list's write actions, shared by the web pages and the app's offline sync,
 * so a tick made on the bus with no signal does exactly what a tick on the website does.
 */
class ShoppingActions
{
    public static function key(string $name): string
    {
        return hash('sha256', SimpleStock::key($name));
    }

    /** Add a typed item ("2 milk", "milk x2") to the household's current week. Returns [name, quantity]. */
    public function add(Household $h, string $text): array
    {
        [$name, $quantity] = ShoppingQuantity::parse($text);
        $week = $h->currentWeek()->toDateString();
        $key = self::key($name);
        DB::table('shopping_extras')->updateOrInsert(['household_id' => $h->id, 'week' => $week, 'item_key' => $key], ['name' => $name, 'source' => 'manual', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shopping_checks')->updateOrInsert(['household_id' => $h->id, 'week' => $week, 'item_key' => $key], ['checked' => false, 'removed' => false, 'stocked_at' => null] + ($quantity ? ['quantity' => $quantity] : []));

        return [$name, $quantity];
    }

    public function check(Household $h, string $week, string $key, bool $checked): void
    {
        DB::table('shopping_checks')->updateOrInsert(['household_id' => $h->id, 'week' => $week, 'item_key' => $key], ['checked' => $checked]);
    }

    /** Set an exact quantity, or step the current one by ±1. Returns the stored quantity. */
    public function quantity(Household $h, string $week, string $key, ?string $quantity, ?int $step = null, bool $hasQuantity = true): ?string
    {
        $where = ['household_id' => $h->id, 'week' => $week, 'item_key' => $key];
        $value = $step !== null
            ? ShoppingQuantity::step(ShoppingQuantity::clean($hasQuantity ? $quantity : DB::table('shopping_checks')->where($where)->value('quantity')), $step)
            : ShoppingQuantity::clean($quantity);
        DB::table('shopping_checks')->updateOrInsert($where, ['quantity' => $value]);

        return $value;
    }

    /** Ticking an item off puts it straight back in the kitchen; unticking puts it back on the list. */
    public function got(Household $h, string $week, string $name, bool $got): void
    {
        DB::transaction(function () use ($h, $week, $name, $got) {
            if (! str_starts_with($name, 'SimplyCook kit: ')) {
                $this->restock($h, $name, $got ? 'plenty' : 'out');
            }
            DB::table('shopping_checks')->updateOrInsert(['household_id' => $h->id, 'week' => $week, 'item_key' => self::key($name)], ['checked' => $got, 'removed' => false, 'stocked_at' => $got ? now() : null]);
        });
    }

    public function remove(Household $h, string $week, string $key, bool $removed): void
    {
        DB::table('shopping_checks')->updateOrInsert(['household_id' => $h->id, 'week' => $week, 'item_key' => $key], ['removed' => $removed]);
    }

    public function restock(Household $h, string $name, string $level = 'plenty', ?string $place = null): void
    {
        $key = SimpleStock::key($name);
        $item = $h->inventory()->lockForUpdate()->get()->first(fn ($i) => SimpleStock::key($i->name) === $key);
        $location = $place ? $this->locationFor($h, $place) : null;
        if ($item) {
            $item->update(['qualitative_stock' => $level, 'quantity' => $level === 'out' ? 0 : null] + ($location ? ['storage_location_id' => $location] : []));
        } else {
            $h->inventory()->create(['name' => trim($name), 'storage_location_id' => $location ?? $this->locationFor($h, FoodDictionary::placeFor($name)), 'qualitative_stock' => $level, 'quantity' => $level === 'out' ? 0 : null, 'unit' => 'each']);
        }
    }

    public function locationFor(Household $h, string $place): int
    {
        return ($h->locations()->where('type', $place)->orderBy('id')->first()
            ?? $h->locations()->create(['name' => FoodDictionary::PLACES[$place], 'type' => $place]))->id;
    }
}
