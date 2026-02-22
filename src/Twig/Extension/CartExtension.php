<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\CartExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CartExtension extends AbstractExtension
{
    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('cart_data', [CartExtensionRuntime::class, 'getCartData']),
            new TwigFunction('cart_count', [CartExtensionRuntime::class, 'getCartCount']),
        ];
    }
}
