<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Tests\Support\FakesCatalogApi;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProductControllerTest extends WebTestCase
{
    use FakesCatalogApi;

    public function testActiveProductPageIsDisplayed(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug);

        $client->request('GET', '/fr/produits/'.$slug);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'AMD Ryzen 7 7800X3D');
    }

    public function testDeactivatedProductPageIsNotFound(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug, active: false);

        $client->request('GET', '/fr/produits/'.$slug);

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnknownProductPageIsNotFound(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr/produits/'.self::newProductSlug());

        self::assertResponseStatusCodeSame(404);
    }
}
