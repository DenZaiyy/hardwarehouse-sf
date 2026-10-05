<?php

namespace App\Twig\Extension;

use App\Enum\CountryList;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class CountryExtension extends AbstractExtension
{
    #[\Override]
    public function getFilters(): array
    {
        return [
            // Code ISO mémorisé (état du tunnel, adresses) vers un pays traduisible : {{ code|country|trans }}
            new TwigFilter('country', static fn (?string $code): ?CountryList => null === $code ? null : CountryList::tryFrom($code)),
        ];
    }
}
