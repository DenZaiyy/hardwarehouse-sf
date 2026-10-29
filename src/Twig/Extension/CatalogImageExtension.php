<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\CatalogImageExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class CatalogImageExtension extends AbstractExtension
{
    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('catalog_image', [CatalogImageExtensionRuntime::class, 'url']),
        ];
    }
}
