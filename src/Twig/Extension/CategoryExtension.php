<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\CategoryExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class CategoryExtension extends AbstractExtension
{
    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('categories', [CategoryExtensionRuntime::class, 'getCategories']),
        ];
    }
}
