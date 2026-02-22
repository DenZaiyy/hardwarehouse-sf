<?php

namespace App\Twig\Runtime;

use App\Service\CartService;
use Twig\Extension\RuntimeExtensionInterface;

class CartExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly CartService $cartService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getCartData(): array
    {
        return [
            'cart' => $this->cartService->getCart(),
            'totals' => $this->cartService->computeTotals(),
            'count' => $this->cartService->getCount(),
        ];
    }

    public function getCartCount(): int
    {
        return $this->cartService->getCount();
    }
}
