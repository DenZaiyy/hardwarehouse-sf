<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Service\Catalog\CatalogImageUrl;
use App\Tests\Support\FakesCatalogApi;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SearchControllerTest extends WebTestCase
{
    use FakesCatalogApi;

    /** Le navigateur affiche la suggestion telle quelle : l'adresse de l'image doit déjà être celle de l'API. */
    public function testSuggestedProductImageIsServedByTheApi(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiListsProducts([self::productPayload($slug)]);

        $client->request('GET', '/fr/search', ['search' => 'ryzen']);

        $suggestions = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($suggestions);
        self::assertSame(
            static::getContainer()->get(CatalogImageUrl::class)->origin().'/images/products/'.$slug.'/thumbnail.webp',
            $suggestions['products'][0]['thumbnail'] ?? null,
        );
    }
}
