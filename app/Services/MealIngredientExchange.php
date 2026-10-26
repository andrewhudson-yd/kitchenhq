<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MealIngredientExchange
{
    public const NAME = 'Standalone meal ingredient exchange';

    public const FIELDS = [
        'ingredient_id', 'ingredient_name', 'quantity', 'unit', 'category',
        'inventory_availability', 'recipe_id', 'recipe_name', 'meal_date', 'meal_type',
        'servings', 'inventory_quantity', 'inventory_unit', 'reported_inventory_min',
        'reported_inventory_max', 'inventory_quantity_confirmed', 'purchase_quantity', 'notes',
    ];

    public function parse(string $input, string $format = 'auto'): array
    {
        if (strlen($input) > 524288 || ! mb_check_encoding($input, 'UTF-8')) {
            $this->fail('Use a UTF-8 CSV or JSON file no larger than 512 KB.');
        }
        $input = trim(preg_replace('/^\xEF\xBB\xBF/', '', $input));
        if ($format === 'auto') {
            $format = str_starts_with($input, '{') || str_starts_with($input, '[') ? 'json' : 'csv';
        }
        $metadata = null;
        if ($format === 'json') {
            try {
                $metadata = json_decode($input, true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $this->fail('The JSON is invalid. Paste the complete JSON object without Markdown fences.');
            }
            if (! is_array($metadata) || array_is_list($metadata)) {
                $this->fail('JSON must be an exchange object containing contract_name, contract_version and ingredients.');
            }
            Validator::make($metadata, [
                'contract_name' => ['required', Rule::in([self::NAME])],
                'contract_version' => ['required', Rule::in(['1.0'])],
                'ingredients' => 'required|array|list|min:1|max:300',
                'meal' => 'sometimes|array',
                'meal.meal_date' => 'sometimes|date_format:Y-m-d',
                'meal.meal_type' => 'sometimes|string|max:40',
                'meal.servings' => 'sometimes|integer|min:1|max:100',
                'meal.timezone' => ['sometimes', Rule::in(['Europe/London'])],
                'recipes' => 'sometimes|array|max:100',
                'recipes.*.recipe_id' => 'required|string|max:100|distinct:strict',
                'recipes.*.recipe_name' => 'required|string|max:200',
                'recipes.*.servings' => 'required|integer|min:1|max:100',
            ])->validate();
            $rows = $metadata['ingredients'];
        } elseif ($format === 'csv') {
            $rows = $this->csv($input);
        } else {
            $this->fail('Choose CSV, JSON or automatic format detection.');
        }
        $normal = [];
        $seen = [];
        $recipes = [];
        foreach (array_values($rows) as $index => $raw) {
            if (! is_array($raw) || array_is_list($raw)) {
                $this->fail('Ingredient row '.($index + 1).' must contain named fields.');
            }
            $row = $this->row($raw, $format, $index + 1);
            $identity = json_encode([$row['recipe_id'], $row['meal_date'], $row['meal_type'], $row['ingredient_id']]);
            if (isset($seen[$identity])) {
                $this->fail('Repeated ingredient_id for the same recipe and meal at row '.($index + 1).'. Combine or correct it before importing.');
            }
            $seen[$identity] = true;
            $recipeKey = json_encode([$row['recipe_id'], $row['meal_date'], $row['meal_type']]);
            $recipe = ['recipe_id' => $row['recipe_id'], 'recipe_name' => $row['recipe_name'], 'meal_date' => $row['meal_date'], 'meal_type' => $row['meal_type'], 'servings' => $row['servings']];
            if (isset($recipes[$recipeKey]) && $recipes[$recipeKey] !== $recipe) {
                $this->fail('A recipe’s name or servings disagree between ingredient rows.');
            }
            $recipes[$recipeKey] = $recipe;
            if ($metadata !== null) {
                foreach (['meal_date', 'meal_type', 'servings'] as $field) {
                    if (isset($metadata['meal'][$field]) && (string) $metadata['meal'][$field] !== (string) $row[$field]) {
                        $this->fail('Row '.($index + 1).' disagrees with the meal’s '.$field.'.');
                    }
                }
                if (isset($metadata['recipes'])) {
                    $declared = collect($metadata['recipes'])->firstWhere('recipe_id', $row['recipe_id']);
                    if (! $declared || $declared['recipe_name'] !== $row['recipe_name'] || (int) $declared['servings'] !== $row['servings']) {
                        $this->fail('Row '.($index + 1).' does not match the declared recipes.');
                    }
                }
            }
            $normal[] = $row;
        }
        if ($metadata !== null && isset($metadata['recipes'])) {
            foreach ($metadata['recipes'] as $declared) {
                if (! collect($normal)->contains('recipe_id', $declared['recipe_id'])) {
                    $this->fail('Every declared recipe must have at least one ingredient row.');
                }
            }
        }
        usort($normal, fn ($a, $b) => strcmp(json_encode([$a['meal_date'], $a['meal_type'], $a['recipe_id'], $a['ingredient_id']]), json_encode([$b['meal_date'], $b['meal_type'], $b['recipe_id'], $b['ingredient_id']])));
        ksort($recipes);

        return ['contract_name' => self::NAME, 'contract_version' => '1.0', 'recipes' => array_values($recipes), 'ingredients' => $normal];
    }

    public function fingerprint(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function csv(string $input): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $input);
        rewind($stream);
        try {
            $headers = fgetcsv($stream, null, ',', '"', '');
            if (! $headers) {
                $this->fail('CSV needs a header row. Download the example for the supported columns.');
            }
            $headers = array_map(fn ($value) => trim((string) $value), $headers);
            if (count($headers) !== count(array_unique($headers)) || array_diff($headers, self::FIELDS)) {
                $this->fail('CSV has duplicate or unrecognised columns. Use the exact headings in the example.');
            }
            $required = ['ingredient_id', 'ingredient_name', 'quantity', 'unit', 'inventory_availability', 'recipe_id', 'recipe_name', 'meal_date', 'meal_type', 'servings'];
            if ($missing = array_diff($required, $headers)) {
                $this->fail('CSV is missing columns: '.implode(', ', $missing).'. Use comma-separated UTF-8 CSV.');
            }
            $rows = [];
            while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
                if ($values === [null] || (count($values) === 1 && trim((string) $values[0]) === '')) {
                    continue;
                }
                if (count($values) !== count($headers)) {
                    $this->fail('CSV row '.(count($rows) + 2).' has a different number of columns. Quote commas inside names and notes.');
                }
                $rows[] = array_combine($headers, $values);
                if (count($rows) > 300) {
                    $this->fail('Import at most 300 ingredient rows at a time.');
                }
            }
            if (! $rows) {
                $this->fail('The CSV has no ingredient rows.');
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }

    private function row(array $raw, string $format, int $number): array
    {
        if (array_diff(array_keys($raw), self::FIELDS)) {
            $this->fail('Row '.$number.' has unrecognised fields. Use the exchange example.');
        }
        $row = array_fill_keys(self::FIELDS, null);
        foreach ($raw as $key => $value) {
            $row[$key] = is_string($value) ? trim($value) : $value;
        }
        foreach (['quantity', 'inventory_quantity', 'reported_inventory_min', 'reported_inventory_max', 'purchase_quantity'] as $key) {
            if ($row[$key] === '') {
                $row[$key] = null;
            }
            if (is_bool($row[$key]) || is_array($row[$key])) {
                $this->fail('Row '.$number.': '.$key.' must be a number or empty.');
            }
        }
        foreach (['category', 'inventory_unit', 'notes'] as $key) {
            $row[$key] ??= '';
        }
        $row['inventory_unit'] = $row['inventory_unit'] === '' ? null : $row['inventory_unit'];
        if ($format === 'csv') {
            $value = $row['inventory_quantity_confirmed'];
            if ($value === null || $value === '') {
                $row['inventory_quantity_confirmed'] = false;
            } elseif (in_array($value, ['true', 'false'], true)) {
                $row['inventory_quantity_confirmed'] = $value === 'true';
            } else {
                $this->fail('Row '.$number.': inventory_quantity_confirmed must be lowercase true or false.');
            }
        } else {
            $row['inventory_quantity_confirmed'] ??= false;
            if (! is_bool($row['inventory_quantity_confirmed'])) {
                $this->fail('Row '.$number.': inventory_quantity_confirmed must be a JSON boolean.');
            }
        }
        $units = ['g', 'ml', 'tsp', 'clove', 'whole', 'nest', 'to taste', 'each', 'portion', 'pack'];
        Validator::make($row, [
            'ingredient_id' => 'required|string|max:100',
            'ingredient_name' => 'required|string|max:200',
            'quantity' => 'nullable|numeric|min:0|max:999999999.999',
            'unit' => ['required', Rule::in($units)],
            'category' => 'nullable|string|max:100',
            'inventory_availability' => ['required', Rule::in(['have', 'buy', 'check'])],
            'recipe_id' => 'required|string|max:100',
            'recipe_name' => 'required|string|max:200',
            'meal_date' => 'required|date_format:Y-m-d',
            'meal_type' => 'required|string|max:40',
            'servings' => 'required|integer|min:1|max:100',
            'inventory_quantity' => 'nullable|numeric|min:0|max:999999999.999',
            'inventory_unit' => ['nullable', Rule::in($units)],
            'reported_inventory_min' => 'nullable|numeric|min:0|max:999999999.999',
            'reported_inventory_max' => 'nullable|numeric|min:0|max:999999999.999',
            'purchase_quantity' => 'nullable|numeric|min:0|max:999999999.999',
            'notes' => 'nullable|string|max:3000',
        ], [], array_combine(self::FIELDS, array_map(fn ($name) => 'row '.$number.' '.str_replace('_', ' ', $name), self::FIELDS)))->validate();
        if (($row['quantity'] === null) !== ($row['unit'] === 'to taste')) {
            $this->fail('Row '.$number.': leave quantity empty only for the unit “to taste”.');
        }
        if ($row['inventory_quantity_confirmed'] !== ($row['inventory_quantity'] !== null)) {
            $this->fail('Row '.$number.': a confirmed inventory quantity needs a measured amount; unconfirmed inventory_quantity must be empty. Use the reported range fields for estimates.');
        }
        $minimum = $row['reported_inventory_min'];
        $maximum = $row['reported_inventory_max'];
        if (($minimum === null) !== ($maximum === null) || ($minimum !== null && (float) $minimum > (float) $maximum)) {
            $this->fail('Row '.$number.': supply both reported range limits, with minimum no greater than maximum.');
        }
        if (($minimum !== null || $row['inventory_quantity'] !== null) && $row['inventory_unit'] === null) {
            $this->fail('Row '.$number.': inventory_unit is required for stock amounts or ranges.');
        }
        foreach (['quantity', 'inventory_quantity', 'reported_inventory_min', 'reported_inventory_max', 'purchase_quantity'] as $key) {
            $row[$key] = $row[$key] === null ? null : (float) $row[$key];
        }
        $row['servings'] = (int) $row['servings'];

        return $row;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['exchange' => $message]);
    }
}
