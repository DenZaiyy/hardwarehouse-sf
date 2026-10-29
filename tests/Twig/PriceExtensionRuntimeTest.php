<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Service\Pricing\PriceCalculator;
use App\Twig\Runtime\PriceExtensionRuntime;
use PHPUnit\Framework\TestCase;

final class PriceExtensionRuntimeTest extends TestCase
{
    public function testPriceIsDisplayedIncludingTaxToTheCentLikeTheCart(): void
    {
        $runtime = new PriceExtensionRuntime(new PriceCalculator());

        // 449,90 € HT × 1,20 = 539,88 € ; 404,91 € HT × 1,20 = 485,892 €, arrondi au centime comme au panier
        self::assertSame(539.88, $runtime->includingTax(449.9));
        self::assertSame(485.89, $runtime->includingTax(404.91));
    }

    public function testDiscountRateIsReadFromBothPrices(): void
    {
        $runtime = new PriceExtensionRuntime(new PriceCalculator());

        // Remise de 10 % : 449,90 € devient 404,91 €
        self::assertSame(10, $runtime->discountRate(449.9, 404.91));
        // Remise fixe de 50 € : 449,90 € devient 399,90 €, soit 11 %, et non « 50 % »
        self::assertSame(11, $runtime->discountRate(449.9, 399.9));
    }

    public function testNoRateWithoutARealReduction(): void
    {
        $runtime = new PriceExtensionRuntime(new PriceCalculator());

        self::assertSame(0, $runtime->discountRate(0.0, 0.0));
        self::assertSame(0, $runtime->discountRate(449.9, 449.9));
    }
}
