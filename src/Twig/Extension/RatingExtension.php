<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Twig\Runtime\RatingExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class RatingExtension extends AbstractExtension
{
    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('rating_summaries', [RatingExtensionRuntime::class, 'summaries']),
        ];
    }
}
