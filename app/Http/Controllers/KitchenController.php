<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\StockMovement;
use App\Services\CookMeal;
use App\Services\ShoppingList;
use App\Services\WeekPlanner;
use App\Support\Food;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KitchenController extends Controller
{
    private function household(Request $r): Household
    {
        return $r->user()->household()->firstOrFail();
    }

    private function week(Request $r): CarbonImmutable
    {
        $r->validate(['week' => 'nullable|date_format:Y-m-d']);

        return CarbonImmutable::parse($r->input('week', today()->toDateString()))->startOfWeek();
    }

    public function dashboard(Request $r, WeekPlanner $planner)
    {
        $h = $this->household($r);
        $week = $this->week($r);

        return view('dashboard', ['h' => $h, 'week' => $week, 'warnings' => $planner->warnings($h, $week),
            'todayMeals' => $h->meals()->with('recipe')->whereDate('date', today())->orderBy('slot')->get(),
            'locations' => $h->locations()->with('items')->get(),
            'low' => $h->inventory()->whereNotNull('quantity')->whereColumn('quantity', '<=', 'minimum_quantity')->count(),
            'unknown' => $h->inventory()->whereNull('quantity')->count(),
            'expiring' => $h->inventory()->with('location')->where('quantity', '>', 0)->whereNotNull('expires_at')->where('expires_at', '<=', today()->addDays(3))->orderBy('expires_at')->get(),
        ]);
    }

    public function inventory(Request $r)
    {
        $h = $this->household($r);

        return view('inventory', ['h' => $h, 'locations' => $h->locations()->with('items')->get(), 'items' => $h->inventory()->with('location')->orderBy('name')->get()]);
    }

    public function saveInventory(Request $r, ?int $id = null)
    {
        $h = $this->household($r);
        $data = $r->validate(['name' => 'required|string|max:150', 'storage_location_id' => ['required', Rule::exists('storage_locations', 'id')->where('household_id', $h->id)], 'quantity' => 'nullable|numeric|min:0|max:999999.999', 'unit' => ['required', Rule::in(Food::UNITS)], 'minimum_quantity' => 'required|numeric|min:0|max:999999.999', 'expires_at' => 'nullable|date_format:Y-m-d', 'notes' => 'nullable|string|max:1000']);
        $data['name'] = Food::name($data['name']);
        $data['quantity'] = $data['quantity'] ?? null;
        DB::transaction(function () use ($h, $data, $id) {
            Household::whereKey($h->id)->lockForUpdate()->firstOrFail();
            $item = $id ? $h->inventory()->whereKey($id)->lockForUpdate()->firstOrFail() : null;
            $old = $item?->quantity;
            // Changing the identity of stock with an audit trail is ambiguous: add a new row instead.
            if ($item && ($item->name !== $data['name'] || $item->unit !== $data['unit'])) {
                throw ValidationException::withMessages(['name' => 'Keep the food name and unit on existing stock. Add a new stock item for a different food or unit.']);
            }
            if ($item) {
                $item->update($data);
            } else {
                $item = $h->inventory()->create($data);
            }
            if ($data['quantity'] !== null) {
                StockMovement::create(['household_id' => $h->id, 'inventory_item_id' => $item->id, 'change' => (float) $data['quantity'] - (float) ($old ?? 0), 'reason' => $old === null ? 'Initial verified count' : 'Manual stock count']);
            }
        });

        return back()->with('status', 'Stock saved. Blank quantities remain unverified.');
    }

    public function addLocation(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:100', 'type' => ['required', Rule::in(['pantry', 'fridge', 'freezer'])]]);
        $this->household($r)->locations()->create($data);

        return back()->with('status', 'Storage location added.');
    }

    public function verifyLocation(Request $r, int $id)
    {
        $h = $this->household($r);
        DB::transaction(function () use ($h, $id) {
            Household::whereKey($h->id)->lockForUpdate()->firstOrFail();
            $location = $h->locations()->whereKey($id)->firstOrFail();
            if ($location->items()->whereNull('quantity')->exists()) {
                throw ValidationException::withMessages(['quantity' => 'Count all unverified items in this location first.']);
            }
            $location->update(['verified_at' => now()]);
        });

        return back()->with('status', 'Location contents confirmed.');
    }

    public function updateLocation(Request $r, int $id)
    {
        $h = $this->household($r);
        $data = $r->validate(['name' => 'required|string|max:100']);
        $h->locations()->findOrFail($id)->update($data);

        return back()->with('status', 'Location renamed.');
    }

    public function deleteLocation(Request $r, int $id)
    {
        $h = $this->household($r);
        DB::transaction(function () use ($h, $id) {
            Household::whereKey($h->id)->lockForUpdate()->firstOrFail();
            $location = $h->locations()->findOrFail($id);
            if ($location->items()->exists()) {
                throw ValidationException::withMessages(['location' => 'Move this location’s stock records before removing it.']);
            }
            $location->delete();
        });

        return back()->with('status', 'Unused storage location removed.');
    }

    public function planner(Request $r, WeekPlanner $planner)
    {
        $h = $this->household($r);
        $week = $this->week($r);

        return view('planner', ['h' => $h, 'week' => $week, 'warnings' => $planner->warnings($h, $week), 'recipes' => $h->recipes()->orderBy('name')->get(), 'meals' => $h->meals()->with('recipe')->whereBetween('date', [$week->toDateString(), $week->addDays(6)->toDateString()])->orderBy('slot')->get()]);
    }

    public function generate(Request $r, WeekPlanner $planner)
    {
        $week = $this->week($r);
        $planner->generate($this->household($r), $week);

        return redirect()->route('planner', ['week' => $week->toDateString()])->with('status', 'Weekly template added. Existing meals were kept. Review portions, ingredients and kit choices.');
    }

    public function saveMeal(Request $r)
    {
        $h = $this->household($r);
        $data = $r->validate(['recipe_id' => ['required', Rule::exists('recipes', 'id')->where('household_id', $h->id)], 'date' => 'required|date_format:Y-m-d', 'slot' => ['required', Rule::in(['packed_lunch', 'lunch', 'early_dinner', 'late_dinner'])], 'audience' => ['required', Rule::in(['Noah', 'Adults', 'You', 'Everyone'])], 'servings' => 'required|integer|min:1|max:20']);
        DB::transaction(function () use ($h, $data) {
            Household::whereKey($h->id)->lockForUpdate()->firstOrFail();
            $key = array_intersect_key($data, array_flip(['date', 'slot', 'audience']));
            $meal = $h->meals()->where($key)->first();
            if ($meal?->cooked_at) {
                throw ValidationException::withMessages(['date' => 'A cooked meal cannot be replaced. Choose another meal slot.']);
            }
            $h->meals()->updateOrCreate($key, $data);
        });

        return redirect()->route('planner', ['week' => CarbonImmutable::parse($data['date'])->startOfWeek()->toDateString()])->with('status', 'Meal planned.');
    }

    public function deleteMeal(Request $r, int $id)
    {
        $h = $this->household($r);
        DB::transaction(function () use ($h, $id) {
            Household::whereKey($h->id)->lockForUpdate()->firstOrFail();
            $meal = $h->meals()->whereKey($id)->firstOrFail();
            if ($meal->cooked_at) {
                throw ValidationException::withMessages(['meal' => 'Cooked meals are kept as a stock audit record.']);
            }
            $meal->delete();
        });

        return back()->with('status', 'Meal removed.');
    }

    public function cook(Request $r, int $id, CookMeal $cook)
    {
        $cook->handle($this->household($r), $id);

        return back()->with('status', 'Meal cooked; stock and kit counts updated.');
    }

    public function recipes(Request $r)
    {
        $h = $this->household($r);

        return view('recipes', ['h' => $h, 'recipes' => $h->recipes()->with('ingredients', 'kit')->orderBy('name')->get(), 'kits' => $h->kits()->orderBy('name')->get(), 'editing' => $r->filled('edit') ? $h->recipes()->with('ingredients')->findOrFail($r->integer('edit')) : null]);
    }

    public function saveRecipe(Request $r, ?int $id = null)
    {
        $h = $this->household($r);
        $data = $r->validate(['name' => 'required|string|max:150', 'servings' => 'required|integer|min:1|max:20', 'instructions' => 'nullable|string|max:5000', 'ingredients' => 'nullable|string|max:10000', 'recipe_kit_id' => ['nullable', Rule::exists('recipe_kits', 'id')->where('household_id', $h->id)], 'is_simplycook' => 'nullable|boolean', 'is_roast' => 'nullable|boolean', 'planning_only' => 'nullable|boolean']);
        $ingredients = [];
        foreach (preg_split('/\R/', trim($data['ingredients'] ?? '')) as $line) {
            if (! trim($line)) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) !== 3 || strlen($parts[0]) > 150 || ! $parts[0] || ! is_numeric($parts[1]) || (float) $parts[1] < 0.001 || (float) $parts[1] > 999999.999 || ! in_array($parts[2], Food::UNITS, true)) {
                throw ValidationException::withMessages(['ingredients' => 'Use one ingredient per line: food name | quantity | unit. Units: g, ml, each, portion, pack.']);
            }
            $ingredients[] = ['name' => Food::name($parts[0]), 'quantity' => round((float) $parts[1], 3), 'unit' => $parts[2]];
        }
        if (! $r->boolean('planning_only') && (! $ingredients || ($r->boolean('is_simplycook') && empty($data['recipe_kit_id'])))) {
            throw ValidationException::withMessages(['ingredients' => 'A ready recipe needs ingredients; SimplyCook also needs a linked kit. Otherwise keep it as a planning placeholder.']);
        }
        unset($data['ingredients']);
        foreach (['is_simplycook', 'is_roast', 'planning_only'] as $flag) {
            $data[$flag] = $r->boolean($flag);
        }
        DB::transaction(function () use ($h, $data, $ingredients, $id) {
            Household::whereKey($h->id)->lockForUpdate()->firstOrFail();
            $recipe = $id ? $h->recipes()->findOrFail($id) : $h->recipes()->make();
            if ($id && $h->meals()->where('recipe_id', $id)->whereNotNull('cooked_at')->exists()) {
                throw ValidationException::withMessages(['name' => 'This recipe has cooked meals. Create a new recipe version to preserve the history.']);
            }
            $recipe->fill($data)->save();
            $recipe->ingredients()->delete();
            $recipe->ingredients()->createMany($ingredients);
        });

        return redirect()->route('recipes')->with('status', 'Recipe saved.');
    }

    public function saveKit(Request $r)
    {
        $h = $this->household($r);
        $data = $r->validate(['name' => 'required|string|max:100', 'brand' => 'required|string|max:80', 'quantity' => 'nullable|integer|min:0|max:9999']);
        $data['name'] = Food::name($data['name']);
        $data['brand'] = Food::name($data['brand']);
        $data['quantity'] = $data['quantity'] ?? null;
        DB::transaction(function () use ($h, $data) {
            Household::whereKey($h->id)->lockForUpdate()->firstOrFail();
            $h->kits()->updateOrCreate(['name' => $data['name'], 'brand' => $data['brand']], $data);
        });

        return back()->with('status', 'Kit count saved. Quantity is the total you own, including duplicates.');
    }

    public function shopping(Request $r, ShoppingList $list)
    {
        $h = $this->household($r);
        $week = $this->week($r);

        return view('shopping', ['h' => $h, 'week' => $week] + $list->forWeek($h, $week));
    }

    public function family(Request $r)
    {
        $h = $this->household($r);

        return view('family', ['h' => $h, 'profiles' => $h->profiles()->get()]);
    }

    public function saveProfile(Request $r, ?int $id = null)
    {
        $h = $this->household($r);
        $data = $r->validate(['name' => 'required|string|max:100', 'age' => 'nullable|integer|min:0|max:120', 'likes' => 'nullable|string|max:2000', 'dislikes' => 'nullable|string|max:2000', 'notes' => 'nullable|string|max:3000']);
        $id ? $h->profiles()->findOrFail($id)->update($data) : $h->profiles()->create($data);

        return back()->with('status', 'Family preferences saved.');
    }

    public function invite(Request $r)
    {
        $code = Str::random(32);
        $this->household($r)->update(['join_code' => hash('sha256',$code)]);

        return back()->with('invite',$code)->with('status','New household invitation created. Previous invitation codes no longer work.');
    }
}
