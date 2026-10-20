<?php

namespace App\Services;

use App\Models\Household;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class WeekPlanner
{
    public function generate(Household $h, CarbonImmutable $week): void
    {
        DB::transaction(function () use ($h, $week) {
            Household::whereKey($h->id)->lockForUpdate()->firstOrFail();
            $recipes = $h->recipes()->get()->keyBy('name');
            $add = function ($day, $slot, $audience, $name, $servings) use ($h, $week, $recipes) {
                if (! isset($recipes[$name])) {
                    return;
                }
                $h->meals()->firstOrCreate(['date' => $week->addDays($day)->toDateString(), 'slot' => $slot, 'audience' => $audience], ['recipe_id' => $recipes[$name]->id, 'servings' => $servings]);
            };
            $noah = ['Chicken pie, baby potatoes and peas', 'Chicken goujons, waffles and peas', 'Steak pie, baby potatoes and broccoli', 'Chicken goujons, chips and broccoli', 'Chicken pie, baby potatoes and peas', 'Chicken goujons, waffles and peas', 'Steak pie, baby potatoes and broccoli'];
            $adults = ['Mild beef and vegetable rice bowl', 'SimplyCook dinner A — choose your kit', 'Pork, potatoes and vegetables', 'SimplyCook dinner B — choose your kit', 'Mild beef and vegetable rice bowl', 'Pork, potatoes and vegetables', 'Sunday roast'];
            foreach (range(0, 6) as $day) {
                $add($day, 'early_dinner', 'Noah', $noah[$day], 1);
                $add($day, 'late_dinner', 'Adults', $adults[$day], 2);
                if ($day < 5) {
                    $add($day, 'packed_lunch', 'Noah', 'Noah’s packed lunch', 1);
                    $add($day, 'lunch', 'You', $day % 2 ? 'Ham salad roll' : 'Freezer lunch with vegetables and potatoes', 1);
                }
            }
        });
    }

    public function warnings(Household $h, CarbonImmutable $week): array
    {
        $meals = $h->meals()->with('recipe')->whereBetween('date', [$week->toDateString(), $week->addDays(6)->toDateString()])->get();
        $warnings = [];
        $kits = $meals->filter(fn ($m) => $m->slot === 'late_dinner' && $m->audience === 'Adults' && $m->date->dayOfWeekIso <= 4 && $m->recipe->is_simplycook)->count();
        if ($kits !== 2) {
            $warnings[] = 'Plan exactly two SimplyCook adult dinners Monday–Thursday (currently '.$kits.').';
        }
        if (! $meals->contains(fn ($m) => $m->date->dayOfWeekIso === 7 && $m->slot === 'late_dinner' && $m->audience === 'Adults' && $m->recipe->is_roast)) {
            $warnings[] = 'Sunday needs an adult roast dinner.';
        }
        $missingNoah = collect(range(0, 6))->filter(fn ($d) => ! $meals->contains(fn ($m) => $m->date->toDateString() === $week->addDays($d)->toDateString() && $m->slot === 'early_dinner' && $m->audience === 'Noah'))->count();
        if ($missingNoah) {
            $warnings[] = $missingNoah.' day(s) still need Noah’s earlier dinner.';
        }

        return $warnings;
    }
}
