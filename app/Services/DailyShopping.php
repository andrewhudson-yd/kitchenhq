<?php

namespace App\Services;

use App\Models\Household;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DailyShopping
{
    public function forWeek(Household $h, CarbonImmutable $week): array
    {
        $stock = $h->inventory()->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', today()->toDateString()))->get();
        $needs = [];
        $add = function (string $name, string $reason) use (&$needs) {
            $key = SimpleStock::key($name);
            $needs[$key] ??= ['name' => $name, 'uses' => []];
            $needs[$key]['uses'][] = $reason;
        };
        $incomplete = [];
        $meals = $h->meals()->with('recipe.ingredients', 'recipe.kit')->whereNull('cooked_at')->whereBetween('date', [$week->toDateString(), $week->addDays(6)->toDateString()])->get();
        foreach ($meals as $meal) {
            $recipe = $meal->recipe;
            $names = $recipe->shopping_ingredients ?? $recipe->ingredients->pluck('name')->all();
            if (! count($names)) {
                $incomplete[] = $recipe->name;
            }
            foreach ($names as $name) {
                $add($name, $recipe->name);
            }
            if ($recipe->kit) {
                $add('SimplyCook kit: '.$recipe->kit->name, 'Check the flavour pots for '.$recipe->name);
            }
        }
        $import = DB::table('household_imports')->where('household_id', $h->id)->where('source_key', 'personal-profile-v1')->first();
        $payload = $import ? json_decode($import->payload, true) : [];
        foreach ($payload['shopping_staples']['items'] ?? [] as $item) {
            $add($item['name'], $item['purpose']);
        }
        // Replenish explicit low/out stock without pretending a measured amount is known.
        foreach ($stock as $item) {
            if (in_array(SimpleStock::level($item), ['low', 'out'], true)) {
                $add($item->name, 'Stock replenishment');
            }
        }
        $extras = DB::table('shopping_extras')->where('household_id', $h->id)->where('week', $week->toDateString())->get();
        $explicit = [];
        foreach ($extras as $extra) {
            $add($extra->name, 'From your shopping-list photo');
            $explicit[SimpleStock::key($extra->name)] = true;
        }
        $checks = DB::table('shopping_checks')->where('household_id', $h->id)->where('week', $week->toDateString())->pluck('checked', 'item_key');
        $stocked = DB::table('shopping_checks')->where('household_id', $h->id)->where('week', $week->toDateString())->whereNotNull('stocked_at')->pluck('item_key')->all();
        $groups = ['buy' => [], 'check' => [], 'have' => []];
        $removed = DB::table('shopping_checks')->where('household_id', $h->id)->where('week', $week->toDateString())->where('removed', true)->pluck('item_key')->all();
        foreach ($needs as $key => $row) {
            if (in_array(hash('sha256', $key), $removed, true)) {
                continue;
            }
            $assessment = SimpleStock::assess($stock, $row['name']);
            if (isset($explicit[$key])) {
                $assessment = ['action' => 'buy', 'reason' => 'Added from your photo'];
            }
            if (str_starts_with($row['name'], 'SimplyCook kit: ')) {
                $kit = $h->kits()->where('name', substr($row['name'], strlen('SimplyCook kit: ')))->first();
                $assessment = $kit && $kit->quantity > 0 ? ['action' => 'have', 'reason' => 'Kit count recorded'] : ['action' => 'check', 'reason' => 'Kit label photographed — check usable pots before buying more'];
            }
            if (in_array(hash('sha256', $key), $stocked, true)) {
                $assessment = ['action' => 'have', 'reason' => 'Bought and saved to Stock'];
            }
            $row += $assessment + ['item_key' => hash('sha256', $key), 'checked' => (bool) ($checks[hash('sha256', $key)] ?? false)];
            $row['uses'] = array_values(array_unique($row['uses']));
            $groups[$assessment['action']][] = $row;
        }
        foreach ($groups as &$rows) {
            usort($rows, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        }
        return ['groups' => $groups, 'incomplete' => array_unique($incomplete)];
    }
}
