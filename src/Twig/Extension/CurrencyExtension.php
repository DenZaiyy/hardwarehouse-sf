<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\CurrencyExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class CurrencyExtension extends AbstractExtension
{
    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('currency', [CurrencyExtensionRuntime::class, 'getCurrency']),
        ];
    }
}
