<?php

namespace App\Service;

/**
 * Názvy hríbikov napísané CELÉ VEĽKÝMI PÍSMENAMI prevedie na Veľké Prvé Písmená.
 *
 *   „LIPTOVSKÝ HRÁDOK, BELÁ“ → „Liptovský Hrádok, Belá“
 *
 * Názvy s malými alebo zmiešanými písmenami („Chopok, KST“, „chata pod Chopkom“)
 * nechá tak — skratky a zámerné veľké písmená v nich zostanú.
 * Používa sa pri pridaní hríbika cez web aj API (nie v administrácii).
 */
final class TitleNormalizer
{
    /** Kratšie „názvy“ (napr. „KÚ“) nechávame tak — môže ísť o skratku. */
    private const MIN_LETTERS = 3;

    public static function fixAllCaps(string $title): string
    {
        $letters = preg_replace('/[^\p{L}]+/u', '', $title) ?? '';

        $isAllCaps = mb_strlen($letters) >= self::MIN_LETTERS
            && mb_strtoupper($letters) === $letters
            && mb_strtolower($letters) !== $letters;

        if (!$isAllCaps) {
            return $title;
        }

        return mb_convert_case(mb_strtolower($title), MB_CASE_TITLE);
    }
}
