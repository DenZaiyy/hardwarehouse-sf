<?php

declare(strict_types=1);

namespace App\Tests\Controller\Payment;

use App\Entity\CartLine;
use App\Tests\Support\CreatesShopEntities;
use App\Tests\Support\FakesCatalogApi;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

final class CartControllerTest extends WebTestCase
{
    use CreatesShopEntities;
    use FakesCatalogApi;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Les messages flash sont lus dans la session, avant qu'une page ne les affiche
        $this->client->followRedirects(false);
    }

    public function testProductDeactivatedSinceThePageWasShownIsNotAdded(): void
    {
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug);
        $form = $this->addToCartForm($slug);

        $this->apiHasProduct($slug, active: false);
        $this->client->submit($form);

        self::assertSame(0, $this->quantityInCarts($productId));
        self::assertSame(["Ce produit n'est plus disponible."], $this->flashes('danger'));
    }

    public function testCatalogOutageIsReportedWithoutTechnicalDetails(): void
    {
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug);
        $form = $this->addToCartForm($slug);

        $this->apiFailsOn('products/'.$slug);
        $this->client->submit($form);

        self::assertSame(0, $this->quantityInCarts($productId));
        self::assertSame(['Le catalogue est momentanément indisponible, merci de réessayer.'], $this->flashes('danger'));
    }

    public function testQuantityAboveTheStockIsNotAdded(): void
    {
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug, stock: 3);

        $this->client->submit($this->addToCartForm($slug), ['quantity' => '5']);

        self::assertSame(0, $this->quantityInCarts($productId));
        self::assertStringContainsString('il ne reste que 3', implode(' ', $this->flashes('danger')));
    }

    public function testQuantityAlreadyInTheCartCountsAgainstTheStock(): void
    {
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug, stock: 3);
        $form = $this->addToCartForm($slug);

        $this->client->submit($form, ['quantity' => '2']);
        self::assertSame(2, $this->quantityInCarts($productId));

        $this->client->submit($form, ['quantity' => '2']);
        self::assertSame(2, $this->quantityInCarts($productId));
        self::assertStringContainsString('dont 2 déjà dans votre panier', implode(' ', $this->flashes('danger')));
    }

    public function testQuantityBelowOneIsNotAdded(): void
    {
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug);

        $this->client->submit($this->addToCartForm($slug), ['quantity' => '-1']);

        self::assertSame(0, $this->quantityInCarts($productId));
        self::assertNotEmpty($this->flashes('danger'));
    }

    public function testIncreaseStartsFromTheQuantityInTheCart(): void
    {
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug, stock: 5);
        $this->client->submit($this->addToCartForm($slug), ['quantity' => '1']);

        // Le navigateur envoyait la quantité affichée : une valeur forgée passait outre le stock
        $this->client->request('POST', '/fr/cart/increase/'.$productId, ['current_qtt' => '1000', '_csrf_token' => 'csrf-token']);

        self::assertSame(2, $this->quantityInCarts($productId));
    }

    public function testIncreaseStopsAtTheStock(): void
    {
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug, stock: 2);
        $this->client->submit($this->addToCartForm($slug), ['quantity' => '2']);

        $this->client->request('POST', '/fr/cart/increase/'.$productId, ['_csrf_token' => 'csrf-token']);

        self::assertSame(2, $this->quantityInCarts($productId));
        self::assertStringContainsString('il ne reste que 2', implode(' ', $this->flashes('danger')));
    }

    public function testIncreaseFollowsARestockSeenWhenAddingAgain(): void
    {
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug, stock: 2);
        $form = $this->addToCartForm($slug);
        $this->client->submit($form, ['quantity' => '2']);

        // Le réassort est lu à l'ajout suivant : le stock connu de la ligne doit suivre
        $this->apiHasProduct($slug, stock: 10);
        $this->client->submit($form, ['quantity' => '3']);
        $this->client->request('POST', '/fr/cart/increase/'.$productId, ['_csrf_token' => 'csrf-token']);

        self::assertSame(6, $this->quantityInCarts($productId));
    }

    public function testDecreaseIsAllowedAboveTheKnownStock(): void
    {
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug, stock: 3);
        $this->client->submit($this->addToCartForm($slug), ['quantity' => '3']);

        // Ligne antérieure au plafonnement : « − » la rapproche du stock, il ne doit jamais être refusé
        $line = $this->entityManager()->getRepository(CartLine::class)->findOneBy(['productId' => $productId]);
        self::assertInstanceOf(CartLine::class, $line);
        $line->setStockSnapshot(1);
        $this->entityManager()->flush();

        $this->client->request('POST', '/fr/cart/decrease/'.$productId, ['_csrf_token' => 'csrf-token']);

        self::assertSame(2, $this->quantityInCarts($productId));
    }

    public function testCartPageStaysAvailableWhenTheCatalogApiFails(): void
    {
        $this->apiFailsOn('categories');

        $this->client->request('GET', '/fr/cart');

        self::assertResponseIsSuccessful();
    }

    private function addToCartForm(string $slug): Form
    {
        return $this->client->request('GET', '/fr/produits/'.$slug)
            ->filter('form[action$="/cart/add"]')
            ->form();
    }

    private function quantityInCarts(string $productId): int
    {
        $lines = $this->entityManager()->getRepository(CartLine::class)->findBy(['productId' => $productId]);

        return array_sum(array_map(static fn (CartLine $line): int => $line->getQuantity() ?? 0, $lines));
    }

    /** @return array<mixed> */
    private function flashes(string $type): array
    {
        $session = $this->client->getRequest()->getSession();
        self::assertInstanceOf(FlashBagAwareSessionInterface::class, $session);

        return $session->getFlashBag()->peek($type);
    }
}
