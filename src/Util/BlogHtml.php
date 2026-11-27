<?php

namespace App\Util;

/**
 * Vyčistí HTML článku (BlogPost::setText) — editor (TipTap) pri vkladaní textu niekedy uloží
 * medzi všetky slová &nbsp; (riadky sa potom nezalamujú a text pretečie zo stĺpca) a prázdne odrážky.
 */
final class BlogHtml
{
    public static function clean(string $html): string
    {
        // Nezalomiteľné medzery → bežné (entita aj znak U+00A0), viacnásobné medzery → jedna.
        $html = str_ireplace(['&nbsp;', '&#160;', '&#xa0;'], ' ', $html);
        $html = str_replace("\u{00A0}", ' ', $html);
        $html = preg_replace('/ {2,}/', ' ', $html);

        // Prázdne odrážky a odseky (len medzery, <br> alebo prázdny <p> vo vnútri).
        $empty = '(?:\s|<br\s*/?>|<p>\s*</p>)*';
        $html = preg_replace("~<li>{$empty}</li>~i", '', $html);
        $html = preg_replace("~<p>{$empty}</p>~i", '', $html);

        return $html;
    }
}
