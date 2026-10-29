<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\PriceExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class PriceExtension extends AbstractExtension
{
    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('ttc', [PriceExtensionRuntime::class, 'includingTax']),
        ];
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('discount_rate', [PriceExtensionRuntime::class, 'discountRate']),
        ];
    }
}
