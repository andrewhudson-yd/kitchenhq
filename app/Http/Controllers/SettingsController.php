<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\StockMovement;
use App\Services\DailyShopping;
use App\Services\SimpleStock;
use App\Support\FoodDictionary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    private function household(Request $r): Household
    {
        return $r->user()->household()->firstOrFail();
    }

    /** Items still to buy this week (bought ones are already back in the kitchen). */
    private function shoppingRows(Household $h, DailyShopping $shopping)
    {
        $groups = $shopping->forWeek($h, $h->currentWeek())['groups'];

        return collect($groups['buy'])->merge($groups['check']);
    }

    public function show(Request $r, DailyShopping $shopping)
    {
        $h = $this->household($r);
        $kitchen = $h->inventory()->with('location')->get()->reject(fn ($item) => SimpleStock::level($item) === 'out');

        return view('settings', [
            'h' => $h,
            'places' => FoodDictionary::PLACES,
            'counts' => $kitchen->countBy(fn ($item) => $item->location->type),
            'kitchenTotal' => $kitchen->count(),
            'shoppingTotal' => count($shopping->forWeek($h, $h->currentWeek())['groups']['buy']),
        ]);
    }

    public function clearShopping(Request $r, DailyShopping $shopping)
    {
        $h = $this->household($r);
        $week = $h->currentWeek()->toDateString();
        $rows = $this->shoppingRows($h, $shopping);
        DB::transaction(function () use ($h, $week, $rows) {
            DB::table('shopping_extras')->where('household_id', $h->id)->where('week', $week)->delete();
            foreach ($rows as $row) {
                DB::table('shopping_checks')->updateOrInsert(['household_id' => $h->id, 'week' => $week, 'item_key' => $row['item_key']], ['removed' => true, 'checked' => false, 'quantity' => null]);
            }
            // Things we ran out of are only kept so they can sit on the list; with the list cleared they can go.
            $this->deleteItems($h, $h->inventory()->get()->filter(fn ($item) => SimpleStock::level($item) === 'out'));
        });

        return redirect()->route('settings')->with('status', 'Shopping list cleared.');
    }

    public function clearKitchen(Request $r)
    {
        $h = $this->household($r);
        $data = $r->validate(['place' => ['required', Rule::in(array_merge(['all'], array_keys(FoodDictionary::PLACES)))]]);
        $items = $h->inventory()->with('location')->get()
            ->reject(fn ($item) => SimpleStock::level($item) === 'out')
            ->filter(fn ($item) => $data['place'] === 'all' || $item->location->type === $data['place']);
        DB::transaction(fn () => $this->deleteItems($h, $items));
        $label = $data['place'] === 'all' ? 'Kitchen' : FoodDictionary::PLACES[$data['place']];

        return redirect()->route('settings')->with('status', $label.' cleared — '.$items->count().' '.str('item')->plural($items->count()).' deleted.');
    }

    private function deleteItems(Household $h, $items): void
    {
        $ids = $items->pluck('id')->all();
        if (! $ids) {
            return;
        }
        // Clearing is a fresh start, so the items' cooking history goes with them.
        StockMovement::where('household_id', $h->id)->whereIn('inventory_item_id', $ids)->delete();
        $h->inventory()->whereKey($ids)->delete();
    }
}
