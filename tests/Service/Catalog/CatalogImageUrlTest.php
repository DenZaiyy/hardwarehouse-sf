<?php

declare(strict_types=1);

namespace App\Tests\Service\Catalog;

use App\Service\Catalog\CatalogImageUrl;
use PHPUnit\Framework\TestCase;

final class CatalogImageUrlTest extends TestCase
{
    public function testImageUploadedToTheApiIsServedByTheApi(): void
    {
        $images = new CatalogImageUrl('https://api.hardwarehouse.fr/api/v1/');

        self::assertSame(
            'https://api.hardwarehouse.fr/images/products/amd-ryzen-5-5600/thumbnail.webp',
            $images->resolve('/images/products/amd-ryzen-5-5600/thumbnail.webp'),
        );
    }

    public function testLocalApiKeepsItsPort(): void
    {
        $images = new CatalogImageUrl('http://localhost:3000/api/v1');

        self::assertSame('http://localhost:3000', $images->origin());
        self::assertSame('http://localhost:3000/images/brands/amd/logo.webp', $images->resolve('images/brands/amd/logo.webp'));
    }

    public function testImageHostedElsewhereIsLeftAsIs(): void
    {
        $images = new CatalogImageUrl('https://api.hardwarehouse.fr/api/v1/');

        self::assertSame('https://picsum.photos/400/400?random=4033', $images->resolve('https://picsum.photos/400/400?random=4033', '@2x'));
    }

    public function testMissingImageHasNoUrl(): void
    {
        $images = new CatalogImageUrl('https://api.hardwarehouse.fr/api/v1/');

        self::assertNull($images->resolve(null));
        self::assertNull($images->resolve(''));
    }

    /**
     * Les tailles que produit ImageUploadService côté API : thumbnail@2x.webp en 800 px, image-1-sm.webp en 600 px.
     * Le slug peut contenir 800 ou 400 (Ryzen 7 5800X3D, Corsair 4000D) sans que le chemin soit touché.
     */
    public function testVariantNamesTheSizeGeneratedByTheApi(): void
    {
        $images = new CatalogImageUrl('https://api.hardwarehouse.fr/api/v1/');

        self::assertSame(
            'https://api.hardwarehouse.fr/images/products/amd-ryzen-7-5800x3d/thumbnail@2x.webp',
            $images->resolve('/images/products/amd-ryzen-7-5800x3d/thumbnail.webp', '@2x'),
        );
        self::assertSame(
            'https://api.hardwarehouse.fr/images/products/corsair-4000d-airflow-mid-tower/image-1-sm.webp',
            $images->resolve('/images/products/corsair-4000d-airflow-mid-tower/image-1.webp', '-sm'),
        );
    }

    public function testApiAddressWithoutHostIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CatalogImageUrl('/api/v1/');
    }
}
