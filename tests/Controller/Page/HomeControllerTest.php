<?php

declare(strict_types=1);

namespace App\Tests\Controller\Page;

use App\Entity\Rating;
use App\Tests\Support\CreatesShopEntities;
use App\Tests\Support\FakesCatalogApi;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Accueil : chaque chiffre et chaque promesse doivent pouvoir se vérifier dans le catalogue ou dans la boutique.
 */
final class HomeControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use CreatesShopEntities;
    use FakesCatalogApi;

    public function testHomePagePromisesNothingItCannotProve(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr');

        self::assertResponseIsSuccessful();
        $page = $client->getCrawler()->filter('main')->text();
        self::assertStringContainsString('Compatibilité vérifiée à chaque choix', $page);
        self::assertStringContainsString('monter ton setup en confiance', $page);
        self::assertStringContainsString('Avis vérifiés', $page);
        // Ce que rien ne prouvait : 2 000 références, une livraison en 24 h, une note de 4,9, des statistiques de recherche
        foreach (['2 000', 'Livraison express', 'Note clients', 'les plus recherchées', 'les plus consultés', 'premium et plus'] as $claim) {
            self::assertStringNotContainsString($claim, $page);
        }
    }

    public function testFiguresComeFromTheCatalogue(): void
    {
        $client = static::createClient();
        $this->apiListsCategories([['slug' => 'processeurs', 'productsCount' => 16], ['slug' => 'watercooling', 'productsCount' => 5]]);
        $this->apiListsBrands(['AMD', 'Intel', 'Corsair']);

        $crawler = $client->request('GET', '/fr');

        // Références des catégories, marques, puis les catégories tant qu'aucun avis n'est publié
        self::assertSame(['21', '3', '2'], $this->figures($crawler));
        self::assertStringContainsString('Catégories', $crawler->filter('[data-controller="homepage--stats-counter"]')->text());
    }

    public function testAverageOfVerifiedReviewsReplacesTheCategoryCount(): void
    {
        $client = static::createClient();
        $this->apiListsCategories([['slug' => 'processeurs', 'productsCount' => 16]]);
        foreach ([5.0, 4.0] as $note) {
            $this->entityManager()->persist((new Rating())->setUser($this->createUser())->setProductId('produit-note')->setNote($note)->setComment('Avis.'));
        }
        $this->entityManager()->flush();

        $crawler = $client->request('GET', '/fr');

        $rating = $crawler->filter('[data-homepage--stats-counter-target="number"]')->last();
        self::assertSame('4.5', $rating->attr('data-value'));
        self::assertSame('4,5/5', self::plain($rating->text()));
        self::assertStringContainsString('Note moyenne de 2 avis vérifiés', $crawler->filter('[data-controller="homepage--stats-counter"]')->text());
    }

    public function testCurrentDiscountIsAnnouncedWithItsEndDate(): void
    {
        $client = static::createClient();
        static::mockTime('2026-11-27 12:00');
        $this->apiListsDiscounts([[
            'id' => 'remise-processeurs',
            'discountAmount' => 15,
            'discountType' => 'PERCENTAGE',
            'active' => true,
            'startDate' => '2026-11-01T00:00:00.000Z',
            'endDate' => '2026-12-31T12:00:00.000Z',
            'category' => ['id' => 'categorie-processeurs', 'name' => 'Processeurs', 'slug' => 'processeurs', 'active' => true],
            'product' => null,
        ]]);

        $crawler = $client->request('GET', '/fr');

        $banner = $crawler->filter('[data-controller~="homepage--promo-motion"]');
        self::assertSame('-15 % sur la catégorie Processeurs', self::plain($banner->filter('h2')->text()));
        self::assertStringContainsString("Jusqu'au 31 décembre 2026", self::plain($banner->text()));
        self::assertSame('/fr/categories/processeurs', $banner->filter('a')->attr('href'));
    }

    public function testNoBannerWithoutACurrentDiscount(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-controller~="homepage--promo-motion"]');
    }

    public function testEnglishHomePageIsTranslated(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en');

        self::assertSelectorTextContains('h1', 'Build a premium setup, part by part.');
        self::assertSelectorTextContains('#home-categories h2', 'Explore the catalogue');
    }

    public function testMenuListsCategoriesInTheMockupOrder(): void
    {
        $client = static::createClient();
        // L'API renvoie la catégorie la plus récente en premier
        $this->apiListsCategories(array_map(static fn (string $slug): array => ['slug' => $slug], ['peripheriques', 'watercooling', 'processeurs', 'cartes-meres']));

        $crawler = $client->request('GET', '/fr');

        $links = array_values(array_unique($crawler->filter('header a[href*="/categories/"]')->each(
            static fn (Crawler $link): string => (string) $link->attr('href'),
        )));
        self::assertSame(['/fr/categories/processeurs', '/fr/categories/cartes-meres', '/fr/categories/watercooling', '/fr/categories/peripheriques'], $links);
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

    /** @return list<string> valeurs du bandeau de chiffres, dans l'ordre */
    private function figures(Crawler $crawler): array
    {
        return $crawler->filter('[data-homepage--stats-counter-target="number"]')->each(
            static fn (Crawler $figure): string => (string) $figure->attr('data-value'),
        );
    }

    private static function plain(string $text): string
    {
        return trim(str_replace(["\u{a0}", "\u{202f}"], ' ', $text));
    }
}
