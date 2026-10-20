<?php

namespace App\Services;

use App\Models\Household;

class HouseholdSetup
{
    public function seed(Household $h): void
    {
        foreach ([['Pantry', 'pantry'], ['Fridge', 'fridge'], ['Freezer drawer 1', 'freezer'], ['Freezer drawer 2', 'freezer'], ['Freezer drawer 3', 'freezer'], ['Freezer drawer 4', 'freezer']] as [$name, $type]) {
            $h->locations()->firstOrCreate(['name' => $name], ['type' => $type]);
        }
        $profiles = [
            ['Noah', 15, 'Chicken or steak pies with baby potatoes; chicken goujons with chips or waffles; broccoli; peas.', 'Fish and chips; carrots; cabbage.', 'Earlier dinner. Packed lunches: submarine rolls, turkey, crisps, chocolate bars and corner yoghurt.'],
            ['Jayne', null, 'Carrots; cabbage; varied meat.', 'Broccoli; spicy food; chicken mince.', 'Later adult dinner. Keep sauces mild.'],
            ['You', null, 'Ham salad rolls: tomato, lettuce, onion and salad cream. Varied meat.', 'Chicken mince.', 'Works from home. Use freezer food for balanced lunches. Runs mostly evenings; wants balanced weight-loss meals with enough energy. Fruit priorities: banana, apples and peaches.'],
        ];
        foreach ($profiles as [$name, $age, $likes, $dislikes, $notes]) {
            $h->profiles()->firstOrCreate(['name' => $name], compact('age', 'likes', 'dislikes', 'notes'));
        }
        // Quantities below are editable meal templates, never claims about current stock.
        $recipes = [
            ['Chicken pie, baby potatoes and peas', [['chicken pie', 1, 'each'], ['baby potatoes', 200, 'g'], ['peas', 80, 'g']]],
            ['Steak pie, baby potatoes and broccoli', [['steak pie', 1, 'each'], ['baby potatoes', 200, 'g'], ['broccoli', 80, 'g']]],
            ['Chicken goujons, waffles and peas', [['chicken goujons', 150, 'g'], ['waffles', 2, 'each'], ['peas', 80, 'g']]],
            ['Chicken goujons, chips and broccoli', [['chicken goujons', 150, 'g'], ['chips', 200, 'g'], ['broccoli', 80, 'g']]],
            ['Ham salad roll', [['submarine roll', 1, 'each'], ['ham', 60, 'g'], ['tomato', 1, 'each'], ['lettuce', 30, 'g'], ['onion', 20, 'g'], ['salad cream', 15, 'g']]],
            ['Noah’s packed lunch', [['submarine roll', 1, 'each'], ['turkey', 60, 'g'], ['crisps', 1, 'pack'], ['chocolate bar', 1, 'each'], ['corner yoghurt', 1, 'each']]],
            ['Freezer lunch with vegetables and potatoes', [['cooked meat', 1, 'portion'], ['baby potatoes', 200, 'g'], ['mixed vegetables', 150, 'g']]],
            ['Mild beef and vegetable rice bowl', [['beef', 150, 'g'], ['rice', 75, 'g'], ['carrots', 100, 'g'], ['cabbage', 80, 'g']]],
            ['Pork, potatoes and vegetables', [['pork', 150, 'g'], ['baby potatoes', 200, 'g'], ['carrots', 100, 'g'], ['cabbage', 80, 'g']]],
            ['Sunday roast', [['roasting joint', 200, 'g'], ['potatoes', 250, 'g'], ['carrots', 80, 'g'], ['cabbage', 80, 'g']]],
            ['SimplyCook dinner A — choose your kit', []],
            ['SimplyCook dinner B — choose your kit', []],
        ];
        foreach ($recipes as [$name, $ingredients]) {
            $recipe = $h->recipes()->firstOrCreate(['name' => $name], [
                'servings' => 1, 'is_simplycook' => str_starts_with($name, 'SimplyCook'),
                'planning_only' => count($ingredients) === 0, 'is_roast' => $name === 'Sunday roast',
                'instructions' => count($ingredients) ? 'Suggested quantities for one serving. Adjust ingredients and portions to your household before cooking. Follow the food packaging or your usual safe cooking method.' : 'Planning placeholder. Replace with a real kit recipe, add all ingredients and link its kit before marking ready to cook.',
            ]);
            if ($recipe->wasRecentlyCreated) {
                foreach ($ingredients as [$food, $quantity, $unit]) {
                    $recipe->ingredients()->create(['name' => $food, 'quantity' => $quantity, 'unit' => $unit]);
                }
            }
        }
    }
}
