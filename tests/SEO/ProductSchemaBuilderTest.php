<?php

declare(strict_types=1);

namespace App\Tests\SEO;

use App\DTO\Api\Products\ProductDto;
use App\SEO\Schema\ProductSchemaBuilder;
use App\Service\Catalog\CatalogImageUrl;
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

    /**
     * Les images envoyées depuis le back-office sont servies par l'API, pas par la boutique : Google doit les
     * trouver à l'adresse de l'API, la miniature dans sa taille de 800 px.
     */
    public function testImagesAreServedByTheApi(): void
    {
        $product = new ProductDto(id: 'p1', name: 'Ryzen', slug: 'ryzen', price: 449.9, thumbnail: '/images/products/ryzen/thumbnail.webp', images: ['/images/products/ryzen/image-1.webp']);

        self::assertSame(
            ['https://api.hardwarehouse.fr/images/products/ryzen/thumbnail@2x.webp', 'https://api.hardwarehouse.fr/images/products/ryzen/image-1.webp'],
            $this->schema($product)['image'],
        );
    }

    private function offerPrice(ProductDto $product): mixed
    {
        $schema = $this->schema($product);
        self::assertIsArray($schema['offers']);

        return $schema['offers']['price'];
    }

    /** @return array<string, mixed> */
    private function schema(ProductDto $product): array
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://hardwarehouse.fr/fr/produits/ryzen');

        return (new ProductSchemaBuilder($urlGenerator, new PriceCalculator(), new CatalogImageUrl('https://api.hardwarehouse.fr/api/v1/')))->build($product);
    }
}
