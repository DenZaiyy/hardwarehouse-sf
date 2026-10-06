<?php

namespace App\Service\Pricing;

/**
 * Montants calculés en centimes, à un seul endroit : le panier affiché, la commande enregistrée et la
 * session Stripe obtiennent les mêmes totaux au centime près.
 */
final class PriceCalculator
{
    public const float VAT_RATE = 0.20;

    /** Prix unitaire TTC en centimes, arrondi une fois par unité : c'est le montant que Stripe facture. */
    public function unitPriceIncludingTax(float $unitPriceExcludingTax): int
    {
        return (int) round($unitPriceExcludingTax * (1 + self::VAT_RATE) * 100);
    }

    /**
     * @param iterable<array{unit_price: float, quantity: int}> $lines prix unitaires hors taxe, remise déduite
     *
     * @return array{subtotal: int, vat: int, total: int} en centimes ; la TVA est l'écart entre TTC et HT
     */
    public function totals(iterable $lines): array
    {
        $subtotal = 0;
        $total = 0;

        foreach ($lines as $line) {
            $subtotal += (int) round($line['unit_price'] * 100) * $line['quantity'];
            $total += $this->unitPriceIncludingTax($line['unit_price']) * $line['quantity'];
        }

        return ['subtotal' => $subtotal, 'vat' => $total - $subtotal, 'total' => $total];
    }

    /** Montant décimal pour les colonnes NUMERIC : 79197 devient « 791.97 ». */
    public static function toDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
