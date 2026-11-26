<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Carrier;
use App\Service\Catalog\CatalogImageUrl;
use App\Tests\Http\ApiMockResponseFactory;
use App\Tests\Support\FakesCatalogApi;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

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

        self::assertSelectorTextSame('.product-specs td', 'Non');
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

        $crawler = $client->request('GET', '/fr/produits/'.$slug);

        // 449,90 € HT × 1,20, le prix que le client retrouvera au panier ; le prix HT n'est qu'une mention
        self::assertStringContainsString('539,88', $crawler->filter('.product-price')->text());
        self::assertStringNotContainsString('449,90', $crawler->filter('.product-price')->text());
        self::assertSame('449,90 € HT', self::plain($crawler->filter('.product-price-excluding-tax')->text()));
    }

    public function testDiscountedPriceIsDisplayedIncludingTax(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        // Remise de 10 % : 449,90 € HT devient 404,91 € HT
        $this->apiHasProduct($slug, price: 449.9, discountPrice: 404.91, discountAmount: 10);

        $infos = $client->request('GET', '/fr/produits/'.$slug)->filter('.product-infos');

        self::assertStringContainsString('539,88', $infos->filter('del')->text());
        self::assertStringContainsString('485,89', $infos->filter('.product-price')->text());
        self::assertStringContainsString('-10%', $infos->text());
        self::assertSame('404,91 € HT', self::plain($infos->filter('.product-price-excluding-tax')->text()));
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

    public function testListedProductImageIsServedByTheApi(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiListsProducts([self::productPayload($slug)]);

        $image = $client->request('GET', '/fr/produits')->filter('a[href$="/produits/'.$slug.'"] img');

        self::assertSame($this->apiImages($slug).'/thumbnail.webp', $image->attr('src'));
        self::assertSame($this->apiImages($slug).'/thumbnail.webp 400w, '.$this->apiImages($slug).'/thumbnail@2x.webp 800w', $image->attr('srcset'));
        self::assertSame('400', $image->attr('width'));
        self::assertSame('400', $image->attr('height'));
    }

    /**
     * La galerie commence par la miniature en 800 px, puis les images de galerie (600 et 1200 px). Le slug
     * contient 800, comme celui du Ryzen 7 5800X3D : l'ancien gabarit remplaçait 800 par 1200 dans toute l'adresse.
     */
    public function testGalleryShowsTheSizesGeneratedByTheApi(): void
    {
        $client = static::createClient();
        $slug = 'amd-ryzen-7-5800x3d-'.bin2hex(random_bytes(4));
        $product = self::productPayload($slug);
        $product['images'] = ['/images/products/'.$slug.'/image-1.webp'];
        ApiMockResponseFactory::respondWith('products/'.$slug, $product);

        $gallery = $client->request('GET', '/fr/produits/'.$slug)->filter('[data-controller="product-gallery"]');

        $images = $this->apiImages($slug);
        self::assertSame($images.'/thumbnail@2x.webp', $gallery->filter('[data-product-gallery-target="mainImage"]')->attr('src'));
        self::assertSame(
            [
                [$images.'/thumbnail@2x.webp', $images.'/thumbnail.webp 400w, '.$images.'/thumbnail@2x.webp 800w'],
                [$images.'/image-1.webp', $images.'/image-1-sm.webp 600w, '.$images.'/image-1.webp 1200w'],
            ],
            $gallery->filter('[data-product-gallery-target="images"]')->each(
                static fn (Crawler $button): array => [$button->attr('data-full-src'), $button->attr('data-srcset')],
            ),
        );
    }

    public function testSharedProductPageShowsItsImage(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug);

        $client->request('GET', '/fr/produits/'.$slug);

        self::assertSelectorExists('meta[property="og:image"][content="'.$this->apiImages($slug).'/thumbnail@2x.webp"]');
    }

    public function testProductWithoutImageShowsThePlaceholder(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $product = self::productPayload($slug);
        $product['thumbnail'] = null;
        ApiMockResponseFactory::respondWith('products/'.$slug, $product);

        $client->request('GET', '/fr/produits/'.$slug);

        self::assertSelectorNotExists('[data-controller="product-gallery"]');
        self::assertSelectorTextContains('main', 'Aucune image disponible');
    }

    public function testBreadcrumbLeadsFromHomeToTheProduct(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug);

        $crawler = $client->request('GET', '/fr/produits/'.$slug);

        $steps = $crawler->filter('nav[aria-label="Fil d\'Ariane"] li');
        // Le séparateur « › » est masqué aux lecteurs d'écran (aria-hidden) : seuls le lien ou la page courante comptent
        self::assertSame(['Accueil', 'Processeurs', 'AMD Ryzen 7 7800X3D'], $steps->each(static fn (Crawler $step): string => trim($step->filter('a, [aria-current]')->text())));
        self::assertSame(['/fr', '/fr/categories/processeurs'], $steps->filter('a')->each(static fn (Crawler $link): ?string => $link->attr('href')));
        self::assertSame('AMD Ryzen 7 7800X3D', $steps->filter('[aria-current="page"]')->text());

        $schemas = $crawler->filter('script[type="application/ld+json"]')->each(static fn (Crawler $script): mixed => json_decode($script->text(), true));
        $breadcrumb = array_values(array_filter($schemas, static fn (mixed $schema): bool => \is_array($schema) && 'BreadcrumbList' === ($schema['@type'] ?? null)));
        self::assertCount(1, $breadcrumb);
        self::assertSame(['Accueil', 'Processeurs', 'AMD Ryzen 7 7800X3D'], array_column($breadcrumb[0]['itemListElement'], 'name'));
        self::assertSame('http://localhost/fr/categories/processeurs', $breadcrumb[0]['itemListElement'][1]['item']);
    }

    public function testBrandIsShownAboveTheName(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug);

        $client->request('GET', '/fr/produits/'.$slug);

        self::assertSelectorTextSame('a.product-brand[href="/fr/marques/amd"]', 'AMD');
    }

    public function testHighlightsSummariseTheFirstCharacteristics(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug, attributes: [
            ['name' => 'Socket', 'type' => 'TEXT', 'value' => 'AM5'],
            ['name' => 'Graphiques intégrés', 'type' => 'BOOLEAN', 'value' => 'true'],
            ['name' => 'Nombre de cœurs', 'type' => 'NUMBER', 'value' => '8'],
            ['name' => 'Fréquence de base (GHz)', 'type' => 'NUMBER', 'value' => '4.2'],
            ['name' => 'TDP (W)', 'type' => 'NUMBER', 'value' => '120'],
            ['name' => 'Cache L3 (MB)', 'type' => 'NUMBER', 'value' => '96'],
        ]);

        $crawler = $client->request('GET', '/fr/produits/'.$slug);

        self::assertSame(
            ['Socket AM5', 'Nombre de cœurs 8', 'Fréquence de base 4,2 GHz', 'TDP 120 W'],
            $crawler->filter('.product-highlights li')->each(static fn (Crawler $pill): string => self::plain($pill->text())),
        );
    }

    public function testCharacteristicsTableWritesTheUnitOnTheValue(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug, attributes: [['name' => 'Fréquence de base (GHz)', 'type' => 'NUMBER', 'value' => '4.2']]);

        $crawler = $client->request('GET', '/fr/produits/'.$slug);

        self::assertSame('Fréquence de base', $crawler->filter('.product-specs th')->text());
        self::assertSame('4,2 GHz', self::plain($crawler->filter('.product-specs td')->text()));
    }

    public function testStockShowsTheQuantityAvailable(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug, stock: 3);

        $client->request('GET', '/fr/produits/'.$slug);

        self::assertSelectorTextSame('.product-stock', 'En stock (3 disponibles)');
        self::assertSelectorExists('.product-infos button[type="submit"]:not([disabled])');
    }

    public function testOutOfStockProductCannotBeAddedToTheCart(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug, stock: 0);

        $client->request('GET', '/fr/produits/'.$slug);

        self::assertSelectorTextSame('.product-stock', 'Rupture de stock');
        self::assertSelectorExists('.product-infos button[type="submit"][disabled]');
    }

    public function testQuantitySelectorStepsWithinTheStock(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug, stock: 3);

        $client->request('GET', '/fr/produits/'.$slug);

        self::assertSelectorExists('[data-controller="quantity"] button[data-action="quantity#decrement"][aria-label="Diminuer la quantité"]');
        self::assertSelectorExists('[data-controller="quantity"] button[data-action="quantity#increment"][aria-label="Augmenter la quantité"]');
        self::assertSelectorExists('input#quantity[data-quantity-target="input"][min="1"][max="3"]');
    }

    public function testReassuranceShowsTheCheapestDeliveryAndTheConfiguratorCheck(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach (['Transporteur express' => '9.90', 'Transporteur standard' => '4.99'] as $name => $price) {
            $entityManager->persist((new Carrier())->setName($name)->setPrice($price));
        }
        $entityManager->flush();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug);

        $reassurance = self::plain($client->request('GET', '/fr/produits/'.$slug)->filter('.product-reassurance')->text());

        self::assertStringContainsString('Livraison suivie dès 4,99 €', $reassurance);
        self::assertStringContainsString('Paiement sécurisé : carte bancaire ou PayPal', $reassurance);
        self::assertStringContainsString('Compatibilité vérifiée dans le configurateur', $reassurance);
    }

    public function testProductOutsideTheConfiguratorRemindsTheWithdrawalPeriod(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug, category: 'ecrans');

        $reassurance = self::plain($client->request('GET', '/fr/produits/'.$slug)->filter('.product-reassurance')->text());

        self::assertStringContainsString('Rétractation sous 14 jours', $reassurance);
        self::assertStringNotContainsString('configurateur', $reassurance);
    }

    public function testTabsGroupDescriptionCharacteristicsAndReviews(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug);

        $crawler = $client->request('GET', '/fr/produits/'.$slug);

        $tabs = $crawler->filter('[role="tablist"] [role="tab"]');
        self::assertSame(['Description', 'Caractéristiques', 'Avis'], $tabs->each(static fn (Crawler $tab): string => trim($tab->text())));
        self::assertSame('Caractéristiques', trim($crawler->filter('[role="tab"][aria-selected="true"]')->text()));
        self::assertCount(1, $crawler->filter('[role="tabpanel"]:not([hidden])'));
    }

    /** Espaces insécables (avant une unité, entre milliers) ramenés à des espaces simples */
    private static function plain(string $text): string
    {
        return trim(str_replace(["\u{a0}", "\u{202f}"], ' ', $text));
    }

    private function apiImages(string $slug): string
    {
        return static::getContainer()->get(CatalogImageUrl::class)->origin().'/images/products/'.$slug;
    }
}
