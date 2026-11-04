<?php

declare(strict_types=1);

namespace App\Tests\DTO\Api\Products;

use App\DTO\Api\Products\ProductSpecDto;
use PHPUnit\Framework\TestCase;

/**
 * Le configurateur annonce le prix que le panier facturera : la remise ne compte que pour un produit mis en avant
 * dont les deux champs de remise sont renseignés, comme dans CartService.
 */
final class ProductSpecDtoTest extends TestCase
{
    public function testPromotedProductCostsItsDiscountPrice(): void
    {
        $product = new ProductSpecDto(id: 'cpu-a', price: 449.9, discountPrice: 399.9, discountAmount: 50.0, promote: true);

        self::assertSame(399.9, $product->effectivePrice());
    }

    public function testDiscountIsIgnoredWhenTheProductIsNotPromoted(): void
    {
        $product = new ProductSpecDto(id: 'cpu-a', price: 449.9, discountPrice: 399.9, discountAmount: 50.0);

        self::assertSame(449.9, $product->effectivePrice());
    }

    public function testDiscountIsIgnoredWithoutItsAmount(): void
    {
        $product = new ProductSpecDto(id: 'cpu-a', price: 449.9, discountPrice: 399.9, promote: true);

        self::assertSame(449.9, $product->effectivePrice());
    }
}
