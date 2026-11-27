<?php

namespace App\Twig;

use App\Service\AreaCatalog;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/** area_slug — slug pohoria alebo kraja pre odkazy na /pohorie/{slug} a /kraj/{slug}. */
class AreaExtension extends AbstractExtension
{
    public function __construct(private readonly AreaCatalog $catalog)
    {
    }

    public function getFilters(): array
    {
        return [new TwigFilter('area_slug', $this->catalog->slug(...))];
    }
}
