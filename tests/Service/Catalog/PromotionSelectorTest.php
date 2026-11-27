<?php

declare(strict_types=1);

namespace App\Tests\Service\Catalog;

use App\Service\Catalog\PromotionSelector;
use App\Tests\Support\FakesCatalogApi;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PromotionSelectorTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use FakesCatalogApi;

    public function testCurrentCategoryDiscountWinsOverEverythingElse(): void
    {
        self::bootKernel();
        static::mockTime('2026-11-27 12:00');
        $this->apiListsDiscounts([
            self::discount('produit-30', 30, product: true),
            self::discount('expiree-20', 20, start: '2026-10-01', end: '2026-11-01'),
            self::discount('future-25', 25, start: '2026-12-01'),
            self::discount('fixe-50', 50, type: 'FIXED'),
            self::discount('categorie-inactive-40', 40, active: false),
            self::discount('categorie-10', 10),
        ]);

        self::assertSame('categorie-10', $this->selector()->featured()?->id);
    }

    public function testProductDiscountIsShownWhenNoCategoryHasOne(): void
    {
        self::bootKernel();
        static::mockTime('2026-11-27 12:00');
        $this->apiListsDiscounts([self::discount('produit-5', 5, product: true), self::discount('produit-12', 12, product: true)]);

        self::assertSame('produit-12', $this->selector()->featured()?->id);
    }

    public function testNothingIsShownWithoutACurrentDiscount(): void
    {
        self::bootKernel();
        static::mockTime('2026-11-27 12:00');
        $this->apiListsDiscounts([self::discount('expiree', 20, start: '2026-10-01', end: '2026-11-01')]);

        self::assertNull($this->selector()->featured());
    }

    private function selector(): PromotionSelector
    {
        $selector = static::getContainer()->get(PromotionSelector::class);
        self::assertInstanceOf(PromotionSelector::class, $selector);

        return $selector;
    }

    /** @return array<string, mixed> remise telle que GET /discounts la renvoie */
    private static function discount(string $id, float $amount, string $type = 'PERCENTAGE', string $start = '2026-11-01', ?string $end = null, bool $product = false, bool $active = true): array
    {
        $target = ['id' => 'cible-'.$id, 'name' => 'Processeurs', 'slug' => 'processeurs', 'active' => $active];

        return [
            'id' => $id,
            'discountAmount' => $amount,
            'discountType' => $type,
            'active' => true,
            'startDate' => $start.'T00:00:00.000Z',
            'endDate' => null === $end ? null : $end.'T00:00:00.000Z',
            'category' => $product ? null : $target,
            'product' => $product ? $target : null,
        ];
    }
}
