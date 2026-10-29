<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Tests\Support\FakesCatalogApi;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProductControllerTest extends WebTestCase
{
    use FakesCatalogApi;

    public function testProductListIsTitledInFrench(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr/produits');

        self::assertSelectorTextSame('h1', 'La liste des produits');
    }

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

    public function testBooleanCharacteristicReadsYesOrNo(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug, attributes: [['name' => 'Incurvé', 'type' => 'BOOLEAN', 'value' => 'false']]);

        $client->request('GET', '/fr/produits/'.$slug);

        self::assertSelectorTextSame('[role="tabpanel"] strong', 'Non');
    }

    public function testQuantityFieldHasALabel(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug);

        $client->request('GET', '/fr/produits/'.$slug);

        self::assertSelectorExists('label[for="quantity"]');
        self::assertSelectorExists('input#quantity[name="quantity"]');
    }

    public function testUnknownProductPageIsNotFound(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr/produits/'.self::newProductSlug());

        self::assertResponseStatusCodeSame(404);
    }

    public function testPriceIsDisplayedIncludingTax(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug, price: 449.9);

        $infos = $client->request('GET', '/fr/produits/'.$slug)->filter('.product-infos')->text();

        // 449,90 € HT × 1,20, le prix que le client retrouvera au panier
        self::assertStringContainsString('539,88', $infos);
        self::assertStringNotContainsString('449,90', $infos);
    }

    public function testDiscountedPriceIsDisplayedIncludingTax(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        // Remise de 10 % : 449,90 € HT devient 404,91 € HT
        $this->apiHasProduct($slug, price: 449.9, discountPrice: 404.91, discountAmount: 10);

        $infos = $client->request('GET', '/fr/produits/'.$slug)->filter('.product-infos');

        self::assertStringContainsString('539,88', $infos->filter('del')->text());
        self::assertStringContainsString('485,89', $infos->text());
        self::assertStringContainsString('-10%', $infos->text());
    }

    public function testFixedDiscountShowsItsActualRate(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        // Remise fixe de 50 € HT : 449,90 € devient 399,90 €, soit 11 %
        $this->apiHasProduct($slug, price: 449.9, discountPrice: 399.9, discountAmount: 50);

        $infos = $client->request('GET', '/fr/produits/'.$slug)->filter('.product-infos')->text();

        self::assertStringContainsString('-11%', $infos);
        self::assertStringNotContainsString('-50%', $infos);
    }

    public function testListedProductShowsItsDiscountIncludingTax(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiListsProducts([self::productPayload($slug, price: 449.9, discountPrice: 404.91, discountAmount: 10)]);

        // La carte est un lien vers la fiche, prix compris
        $card = $client->request('GET', '/fr/produits')->filter('a[href$="/produits/'.$slug.'"]');

        self::assertCount(1, $card);
        self::assertStringContainsString('539,88', $card->filter('del')->text());
        self::assertStringContainsString('485,89', $card->text());
        self::assertStringContainsString('-10%', $card->text());
    }
}
