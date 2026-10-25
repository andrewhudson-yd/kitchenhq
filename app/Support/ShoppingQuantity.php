<?php

namespace App\Support;

class ShoppingQuantity
{
    private const UNITS = 'g|kg|ml|l|litres?|liters?|pints?|packs?|tins?|cans?|bottles?|bags?|boxes?|jars?|loaf|loaves|bunch(?:es)?|dozen';

    /** Split "2 milk", "milk x2" or "500g mince" into [name, quantity]. */
    public static function parse(string $text): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if (preg_match('/^(\d+(?:\.\d+)?\s*(?:'.self::UNITS.')?)\s*(?:x|×)?\s+(\D.*)$/iu', $text, $m)) {
            return [trim($m[2]), self::clean($m[1])];
        }
        if (preg_match('/^(.*\D)\s+(?:x|×)\s*(\d+(?:\.\d+)?)$/iu', $text, $m) || preg_match('/^(.*\D)\s+(\d+(?:\.\d+)?\s*(?:'.self::UNITS.'))$/iu', $text, $m)) {
            return [trim($m[1]), self::clean($m[2])];
        }

        return [$text, null];
    }

    public static function clean(?string $quantity): ?string
    {
        $quantity = trim(preg_replace(['/\s+/u', '/^[x×]\s*(?=\d)/iu'], [' ', ''], (string) $quantity));
        // One of something is the default, so it needs no quantity.
        if (in_array($quantity, ['', '0', '1', '×1', 'x1'], true)) {
            return null;
        }

        return mb_substr(preg_replace('/^(\d+(?:\.\d+)?) (g|kg|ml|l)$/iu', '$1$2', $quantity), 0, 30);
    }

    /** Add or take one from the number in a quantity, keeping its unit: "2" → "3", "2 tins" → "1 tin", "500g" → "600g". */
    public static function step(?string $quantity, int $by): ?string
    {
        $quantity = trim((string) $quantity);
        if (! preg_match('/^(\d+)(.*)$/u', $quantity, $m)) {
            return $by > 0 && $quantity === '' ? '2' : ($quantity === '' ? null : $quantity);
        }
        $unit = $m[2];
        $size = preg_match('/^(g|ml)$/i', $unit) ? 100 : 1;
        $next = (int) $m[1] + $by * $size;
        // One of something is the default, so it needs no quantity.
        if ($next < $size || ($next === 1 && $unit === '')) {
            return null;
        }
        if (preg_match('/^ ([a-z]+)$/iu', $unit, $u)) {
            $word = match (strtolower($u[1])) {
                'loaf', 'loaves' => 'loaf',
                default => preg_replace(['/(x|ch|sh)es$/i', '/s$/i'], ['$1', ''], $u[1]),
            };
            $plural = $word === 'loaf' ? 'loaves' : $word.(preg_match('/(x|ch|sh)$/i', $word) ? 'es' : 's');
            $unit = ' '.($next === 1 ? $word : $plural);
        }

        return $next.$unit;
    }

    /** How a quantity reads on the list: bare numbers become "×2". */
    public static function label(?string $quantity): ?string
    {
        return $quantity === null ? null : (preg_match('/^\d+(?:\.\d+)?$/', $quantity) ? '×'.$quantity : $quantity);
    }
}
