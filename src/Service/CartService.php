<?php

namespace App\Service;

class CartService
{
    /**
     * @param array<int, array{price_ht: float, quantity: int}> $items
     *
     * @return array{subtotal: float, vat_rate: float, vat_amount: float, total: float}
     */
    public function computeTotals(array $items): array
    {
        $subtotal = 0;
        $vatRate = 0.20;

        foreach ($items as $item) {
            $subtotal += $item['price_ht'] * $item['quantity'];
        }

        $vatAmount = $subtotal * $vatRate;
        $total = $subtotal + $vatAmount;

        return [
            'subtotal' => $subtotal,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'total' => $total,
        ];
    }
}
