<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Tests\Support\FakesCatalogApi;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BrandControllerTest extends WebTestCase
{
    use FakesCatalogApi;

    public function testUnknownBrandPageIsNotFound(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr/marques/marque-inconnue');

        self::assertResponseStatusCodeSame(404);
    }
}
