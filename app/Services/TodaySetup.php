<?php

namespace App\Services;

use App\Models\Household;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TodaySetup
{
    public function apply(Household $h): void
    {
        DB::transaction(function () use ($h) {
            if (DB::table('household_imports')->where('household_id', $h->id)->where('source_key', 'daily-use-2026-10-20')->exists()) {
                return;
            }
            $locations = [];
            foreach (['Cupboard' => 'pantry', 'Fridge' => 'fridge', 'Freezer' => 'freezer'] as $name => $type) {
                $locations[$type] = $h->locations()->firstOrCreate(['name' => $name], ['type' => $type])->id;
            }
            $items = json_decode(file_get_contents(database_path('data/kitchen-hq/inventory.json')), true, 512, JSON_THROW_ON_ERROR)['items'];
            $category = collect($items)->keyBy('name');
            foreach ($h->inventory()->with('location')->get() as $item) {
                if ($item->location->name !== 'Imported stock — location unverified') {
                    continue;
                }
                $c = $category[$item->name]['category'] ?? '';
                $type = str_starts_with($c, 'Frozen') || str_starts_with($item->name, 'Frozen') || $c === 'Meat, fish & seafood' ? 'freezer' : ($c === 'Chilled' ? 'fridge' : 'pantry');
                if (in_array($item->name, ['Mango chutney', 'Pickled gherkins', 'Mustard / horseradish', 'Other chutneys / condiments', 'Sweet chilli sauce', 'Tomato ketchup', 'Mayonnaise'], true)) {
                    $type = 'fridge';
                }
                $item->update(['storage_location_id' => $locations[$type], 'notes' => ($item->notes ?? '').' Storage grouping inferred from photo/category; move it if needed.']);
            }
            $photoItems = [
                'fridge' => ['Halloumi', 'Mint jelly', 'English mustard', 'Horseradish sauce', 'American style mustard', 'Salad cream', 'Brown sauce', 'Strawberries'],
                'pantry' => ['Crunchy peanut butter', 'Ambrosia Devon custard', 'Mexican chilli blend', 'Ground turmeric', 'Cornish sea salt flakes'],
            ];
            $known = $h->inventory()->get()->map(fn ($i) => SimpleStock::key($i->name))->all();
            foreach ($photoItems as $type => $names) {
                foreach ($names as $name) {
                    if (! in_array(SimpleStock::key($name), $known, true)) {
                        $h->inventory()->firstOrCreate(['name' => $name], ['storage_location_id' => $locations[$type], 'quantity' => null, 'qualitative_stock' => null, 'unit' => 'each', 'notes' => 'Visible in user photograph. Remaining amount and photo date unknown; choose a stock level.']);
                    }
                }
            }
            $h->forceFill(['plan_starts_on' => '2026-10-20'])->save();
            foreach ($h->profiles()->get() as $profile) {
                $raw = $profile->notes ?? '';
                $lastBrace = strrpos($raw, '}');
                $details = $lastBrace === false ? null : json_decode(substr($raw, 0, $lastBrace + 1), true);
                if (! is_array($details)) {
                    continue;
                }
                $notes = match ($profile->name) {
                    'You' => 'Works from home. Use freezer food for lunches. Runs mostly in the evenings; wants balanced meals with enough energy for running.',
                    'Jayne' => 'Later adult dinner. Keep spicy dishes mild. Likes apples and peaches.',
                    'Noah' => 'Earlier dinner. Packed lunches: submarine rolls, turkey slices, crisps and chocolate bars. Likes bananas and corner yoghurts. Serve broccoli or peas with dinner.',
                    default => $profile->notes,
                };
                $profile->update(['notes' => $notes.' Allergy information has not been supplied.']);
            }
            $plan = json_decode(file_get_contents(database_path('data/kitchen-hq/sample_week.json')), true, 512, JSON_THROW_ON_ERROR);
            $ingredients = [
                'Tue fish chips mushy peas' => ['cod', 'chips', 'mushy peas'],
                'Wed lamb keema naan' => ['lamb mince', 'red onion', 'chopped tomatoes', 'peas', 'naan'],
                'Thu Thai green chicken curry rice' => ['chicken', 'Thai green curry sauce', 'rice', 'peppers', 'coconut milk'],
                'Fri sausage tomato pasta' => ['sausages', 'chopped tomatoes', 'pasta'],
                'Sat freezer tapas' => ['vegetable spring rolls', 'duck spring rolls', 'mozzarella sticks', 'halloumi fries', 'onion rings'],
                'Sun roast' => ['roasting joint', 'potatoes', 'carrots', 'cabbage', 'gravy granules'],
                'Mon frozen cod potatoes cabbage' => ['cod', 'potatoes', 'cabbage'],
                'Tue chicken pie baby potatoes broccoli' => ['chicken pie', 'baby potatoes', 'broccoli'],
                'Wed jacket potato cheese broccoli/peas' => ['potatoes', 'cheese', 'broccoli', 'peas'],
                'Thu goujons chips broccoli' => ['goujons', 'chips', 'broccoli'],
                'Fri sausages waffles broccoli/peas' => ['sausages', 'waffles', 'broccoli', 'peas'],
                'Sat dippers chips broccoli/peas' => ['dippers', 'chips', 'broccoli', 'peas'],
                'Sun roast potatoes broccoli/peas' => ['roasting joint', 'potatoes', 'broccoli', 'peas'],
                'Mon chicken pie baby potatoes broccoli' => ['chicken pie', 'baby potatoes', 'broccoli'],
            ];
            $titles = ['Tue fish chips mushy peas' => 'Fish, chips & mushy peas', 'Wed lamb keema naan' => 'Lamb keema & naan', 'Thu Thai green chicken curry rice' => 'Thai green chicken curry & rice', 'Fri sausage tomato pasta' => 'Sausage & tomato pasta', 'Sat freezer tapas' => 'Freezer tapas', 'Sun roast' => 'Sunday roast', 'Mon frozen cod potatoes cabbage' => 'Cod, potatoes & cabbage'];
            foreach (['adult_dinners' => ['Adults', 'late_dinner', 2], 'noah_dinners' => ['Noah', 'early_dinner', 1]] as $section => [$audience, $slot, $servings]) {
                foreach ($plan[$section] as $day => $sourceName) {
                    $title = $titles[$sourceName] ?? ucfirst(preg_replace('/^(Tue|Wed|Thu|Fri|Sat|Sun|Mon) /', '', $sourceName));
                    $recipe = $h->recipes()->firstOrCreate(['name' => $title], ['servings' => $servings, 'planning_only' => true, 'is_roast' => str_starts_with($sourceName, 'Sun ')]);
                    $recipe->forceFill(['shopping_ingredients' => $ingredients[$sourceName], 'instructions' => 'User-confirmed weekly meal, 20–26 October 2026. Ingredient names are a shopping outline, not a fully transcribed recipe. Adjust ingredients and amounts to suit. No stock is automatically deducted.'])->save();
                    $kitName = match ($sourceName) {
                        'Wed lamb keema naan' => 'Keema Matar',
                        'Thu Thai green chicken curry rice' => 'Thai Green Curry',
                        default => null,
                    };
                    if ($kitName && $kit = $h->kits()->where('name', $kitName)->first()) {
                        $recipe->update(['is_simplycook' => true, 'recipe_kit_id' => $kit->id]);
                    }
                    $h->meals()->firstOrCreate(['date' => CarbonImmutable::parse('2026-10-20')->addDays($day)->toDateString(), 'slot' => $slot, 'audience' => $audience], ['recipe_id' => $recipe->id, 'servings' => $servings]);
                }
            }
            foreach (range(0, 6) as $day) {
                $date = CarbonImmutable::parse('2026-10-20')->addDays($day);
                if ($date->isWeekend()) {
                    continue;
                }
                foreach ([['Noah', 'packed_lunch', 'Noah’s packed lunch', ['Submarine rolls', 'Turkey slices', 'Crisps', 'Chocolate bars']], ['You', 'lunch', 'Ham salad roll', ['ham', 'bread rolls / buns', 'lettuce', 'tomatoes', 'fresh onion', 'salad cream']]] as [$audience, $slot, $name, $needs]) {
                    $recipe = $h->recipes()->firstOrCreate(['name' => $name], ['servings' => 1, 'planning_only' => true, 'instructions' => 'Editable lunch suggestion based on imported preferences; use freezer food when preferred.']);
                    $recipe->forceFill(['shopping_ingredients' => $needs])->save();
                    $h->meals()->firstOrCreate(['date' => $date->toDateString(), 'slot' => $slot, 'audience' => $audience], ['recipe_id' => $recipe->id, 'servings' => 1]);
                }
            }
            DB::table('household_imports')->insert(['household_id' => $h->id, 'source_key' => 'daily-use-2026-10-20', 'payload' => json_encode(['plan' => $plan, 'confirmation' => 'User approved this plan in Codex on 2026-10-20', 'shopping_outline' => 'Ingredient names derived from confirmed meal names; no measured quantities asserted', 'lunches' => 'Editable suggestions from imported preferences', 'photo_additions' => $photoItems]), 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}
