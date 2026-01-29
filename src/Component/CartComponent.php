<?php

namespace App\Component;

use App\Service\CartService;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('cart')]
final class CartComponent
{
    use DefaultActionTrait;

    /** @var array<int, array{id: int, quantity: int, price_ht: float, name: string}> */
    #[LiveProp(writable: true)]
    public array $items = [];

    /** @var array{subtotal: float, vat_rate: float, vat_amount: float, total: float} */
    public array $totals = [
        'subtotal' => 0.0,
        'vat_rate' => 0.20,
        'vat_amount' => 0.0,
        'total' => 0.0,
    ];

    public function __construct(
        private readonly CartService $cartService
    ) {
    }

    public function mount(): void
    {
        $this->recalculate();
    }

    #[LiveAction]
    public function increase(#[LiveArg] int $id): void
    {
        foreach ($this->items as &$item) {
            if ($item['id'] === $id) {
                $item['quantity']++;
                break;
            }
        }

        $this->recalculate();
    }

    #[LiveAction]
    public function decrease(#[LiveArg] int $id): void
    {
        foreach ($this->items as &$item) {
            if ($item['id'] === $id && $item['quantity'] > 1) {
                $item['quantity']--;
                break;
            }
        }

        $this->recalculate();
    }

    #[LiveAction]
    public function remove(#[LiveArg] int $id): void
    {
        $this->items = array_filter(
            $this->items,
            static fn (array $item): bool => $item['id'] !== $id
        );

        $this->recalculate();
    }

    private function recalculate(): void
    {
        $this->totals = $this->cartService->computeTotals($this->items);
    }
}
