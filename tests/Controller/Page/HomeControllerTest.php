<?php

declare(strict_types=1);

namespace App\Tests\Controller\Page;

use App\Tests\Support\FakesCatalogApi;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    use FakesCatalogApi;

    public function testHomePageUsesTheMockupWording(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr');

        self::assertResponseIsSuccessful();
        $page = $client->getCrawler()->filter('main')->text();
        self::assertStringContainsString('Compatibilité vérifiée à chaque choix', $page);
        self::assertStringContainsString('Accède directement aux univers les plus consultés de la boutique.', $page);
        self::assertStringContainsString('monter ton setup en confiance', $page);
        // Notes de conception restées dans la page, visibles par les clients
        self::assertStringNotContainsString('premium et plus', $page);
    }
}
