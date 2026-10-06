<?php

declare(strict_types=1);

namespace App\Tests\Service\Pricing;

use App\Service\Pricing\PriceCalculator;
use PHPUnit\Framework\TestCase;

final class PriceCalculatorTest extends TestCase
{
    public function testUnitPriceIncludingTaxIsRoundedToTheCent(): void
    {
        // 219,99 € HT × 1,20 = 263,988 € : un prix TTC ne s'affiche ni ne se facture à la fraction de centime
        self::assertSame(26399, (new PriceCalculator())->unitPriceIncludingTax(219.99));
    }

    public function testTotalIsTheSumOfTheRoundedLines(): void
    {
        $totals = (new PriceCalculator())->totals([
            ['unit_price' => 219.99, 'quantity' => 3],
            ['unit_price' => 183.33, 'quantity' => 1],
        ]);

        // Lignes TTC : 3 × 263,99 € et 1 × 220,00 €, soit 1 011,97 € ; la TVA est l'écart avec le hors taxe
        self::assertSame(['subtotal' => 84330, 'vat' => 16867, 'total' => 101197], $totals);
    }
}
