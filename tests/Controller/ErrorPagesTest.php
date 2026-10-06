<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\FakesCatalogApi;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ErrorPagesTest extends WebTestCase
{
    use FakesCatalogApi;

    public function testCatalogOutageAnswersServiceUnavailable(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiFailsOn('products/'.$slug);

        $client->request('GET', '/fr/produits/'.$slug);

        // Une API en panne n'est pas une erreur de la boutique : 503, page « Catalogue momentanément indisponible »
        self::assertResponseStatusCodeSame(503);
    }
}
