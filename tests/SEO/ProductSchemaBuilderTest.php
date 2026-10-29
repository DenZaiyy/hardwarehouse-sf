<?php

declare(strict_types=1);

namespace App\Tests\SEO;

use App\DTO\Api\Products\ProductDto;
use App\SEO\Schema\ProductSchemaBuilder;
use App\Service\Pricing\PriceCalculator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProductSchemaBuilderTest extends TestCase
{
    public function testOfferPriceIsThePriceTheCustomerPays(): void
    {
        // 449,90 € HT dans l'API : Google doit lire le prix TTC affiché sur la fiche
        self::assertSame('539.88', $this->offerPrice(new ProductDto(id: 'p1', name: 'Ryzen', slug: 'ryzen', price: 449.9)));
    }

    public function testOfferPriceIncludesTheDiscount(): void
    {
        $product = new ProductDto(id: 'p1', name: 'Ryzen', slug: 'ryzen', price: 449.9, discountPrice: 404.91, discountAmount: 10, promote: true);

        self::assertSame('485.89', $this->offerPrice($product));
    }

    private function offerPrice(ProductDto $product): mixed
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://hardwarehouse.fr/fr/produits/ryzen');

        $schema = (new ProductSchemaBuilder($urlGenerator, new PriceCalculator()))->build($product);
        self::assertIsArray($schema['offers']);

        return $schema['offers']['price'];
    }
}
