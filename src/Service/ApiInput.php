<?php

namespace App\Service;

/**
 * Bezpečné čítanie čísel z requestov API. Appka môže poslať čokoľvek (prázdny reťazec, text,
 * desatinnú čiarku) — predtým to padalo na TypeError v setteroch entity a API vracalo 500.
 */
final class ApiInput
{
    /**
     * Číslo v rozsahu <$min, $max>, alebo null, ak hodnota nie je platné číslo.
     * Akceptuje aj desatinnú čiarku a medzery okolo („ 48,1486 “).
     */
    public static function number(mixed $value, float $min = -INF, float $max = INF): ?float
    {
        if (is_int($value) || is_float($value)) {
            $number = (float) $value;
        } elseif (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
            if (!is_numeric($value)) {
                return null;
            }
            $number = (float) $value;
        } else {
            return null;
        }

        if (!is_finite($number) || $number < $min || $number > $max) {
            return null;
        }

        return $number;
    }

    public static function latitude(mixed $value): ?float
    {
        return self::number($value, -90, 90);
    }

    public static function longitude(mixed $value): ?float
    {
        return self::number($value, -180, 180);
    }

    /**
     * Kladné celé číslo (ID), alebo null.
     */
    public static function id(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (is_string($value) && ctype_digit(trim($value))) {
            $id = (int) trim($value);

            return $id > 0 ? $id : null;
        }

        return null;
    }
}
