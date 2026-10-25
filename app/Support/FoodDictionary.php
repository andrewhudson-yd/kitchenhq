<?php

namespace App\Support;

class FoodDictionary
{
    public const PLACES = ['fridge' => 'Fridge', 'freezer' => 'Freezer', 'pantry' => 'Cupboard'];

    private static ?array $foods = null;

    /** @return array<string, string> food name => usual place (fridge, freezer, pantry) */
    public static function all(): array
    {
        if (self::$foods === null) {
            $data = json_decode(file_get_contents(database_path('data/kitchen-hq/food_dictionary.json')), true, 512, JSON_THROW_ON_ERROR);
            self::$foods = [];
            foreach (array_keys(self::PLACES) as $place) {
                foreach ($data[$place] ?? [] as $name) {
                    self::$foods[$name] ??= $place;
                }
            }
        }

        return self::$foods;
    }

    public static function placeFor(string $name, string $fallback = 'pantry'): string
    {
        $wanted = Food::name($name);
        foreach (self::all() as $food => $place) {
            if (Food::name($food) === $wanted) {
                return $place;
            }
        }

        return $fallback;
    }
}
