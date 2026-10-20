<?php

namespace App\Support;

class Food
{
    public const UNITS = ['g', 'ml', 'each', 'portion', 'pack'];

    public static function name(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }

    public static function amount(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }
}
