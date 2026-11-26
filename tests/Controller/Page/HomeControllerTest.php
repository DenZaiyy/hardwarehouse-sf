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

    public function testCategoryCardShowsTheCategoryVisualWhenTheApiHasOne(): void
    {
        $client = static::createClient();
        $this->apiListsCategories([
            ['slug' => 'processeurs', 'name' => 'Processeurs', 'logo' => '/images/categories/processeurs/logo.webp'],
            ['slug' => 'ssd', 'name' => 'SSD'],
        ]);

        $crawler = $client->request('GET', '/fr');

        // Une seule image : la catégorie sans visuel garde le dégradé de la charte
        $visual = $crawler->filter('#home-categories img');
        self::assertCount(1, $visual);
        self::assertStringEndsWith('/images/categories/processeurs/logo.webp', (string) $visual->attr('src'));
        self::assertStringContainsString('/images/categories/processeurs/logo@2x.webp 800w', (string) $visual->attr('srcset'));
        // Décorative : le texte de la carte nomme déjà la catégorie
        self::assertSame('', $visual->attr('alt'));
    }
}
