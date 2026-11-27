<?php

namespace App\Twig;

use Symfony\Component\String\TruncateMode;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

use function Symfony\Component\String\u;

/**
 * meta_text(max) — text pripravený do <meta name="description">: bez HTML, bez zalomení
 * a viacnásobných medzier, orezaný na celé slová s „…“ (Google zobrazí ~155 znakov).
 */
class SeoExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('meta_text', $this->metaText(...)),
        ];
    }

    public function metaText(?string $text, int $max = 155): string
    {
        $plain = u(strip_tags(html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5)))
            ->replaceMatches('/\s+/u', ' ')
            ->trim();

        if ($plain->length() <= $max) {
            return $plain->toString();
        }

        // WordBefore = orezať pred slovom, ktoré by prekročilo limit (výsledok nikdy nie je dlhší ako $max).
        return $plain->truncate($max, '…', TruncateMode::WordBefore)->toString();
    }
}
