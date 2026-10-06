<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\CartLine;
use App\Service\CartService;
use App\Tests\Support\CreatesShopEntities;
use App\Tests\Support\FakesCatalogApi;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Avant le paiement, chaque produit du panier est relu dans l'API : ce qui a changé depuis l'ajout
 * au panier est corrigé, et le client en est prévenu.
 */
final class CartRevalidationTest extends KernelTestCase
{
    use CreatesShopEntities;
    use FakesCatalogApi;

    public function testUnchangedCartProducesNoNotice(): void
    {
        $slug = self::newProductSlug();
        $this->visitorCart($slug, $this->apiHasProduct($slug, stock: 5, price: 449.9), quantity: 2, unitPrice: '449.90');

        self::assertSame([], $this->cartService()->revalidate());
    }

    public function testProductWithdrawnFromSaleIsRemoved(): void
    {
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug, active: false);
        $this->visitorCart($slug, $productId, quantity: 1, unitPrice: '449.90');

        $notices = $this->cartService()->revalidate();

        self::assertSame(["« AMD Ryzen 7 7800X3D » n'est plus disponible et a été retiré de votre panier."], $notices);
        self::assertSame([], $this->linesFor($productId));
    }

    public function testQuantityIsCappedAtTheStock(): void
    {
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug, stock: 1);
        $this->visitorCart($slug, $productId, quantity: 3, unitPrice: '449.90');

        $notices = $this->cartService()->revalidate();

        self::assertSame(['Il ne reste que 1 exemplaire(s) de « AMD Ryzen 7 7800X3D » : la quantité a été ajustée.'], $notices);
        self::assertSame(1, $this->linesFor($productId)[0]->getQuantity());
    }

    public function testNewPriceIsAppliedAndAnnounced(): void
    {
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug, price: 499.9);
        $this->visitorCart($slug, $productId, quantity: 1, unitPrice: '449.90');

        $notices = $this->cartService()->revalidate();

        self::assertCount(1, $notices);
        // Prix TTC, comme le client les voit : 539,88 € avant, 599,88 € maintenant
        self::assertStringContainsString('539,88', $notices[0]);
        self::assertStringContainsString('599,88', $notices[0]);
        self::assertSame('499.90', $this->linesFor($productId)[0]->getUnitPriceSnapshot());
    }

    public function testCatalogueOutageStopsThePayment(): void
    {
        $slug = self::newProductSlug();
        $this->visitorCart($slug, $this->apiHasProduct($slug), quantity: 1, unitPrice: '449.90');
        $this->apiFailsOn('products/'.$slug);

        $this->expectExceptionMessage('Le catalogue est momentanément indisponible, merci de réessayer.');
        $this->cartService()->revalidate();
    }

    private function visitorCart(string $slug, string $productId, int $quantity, string $unitPrice): void
    {
        $token = bin2hex(random_bytes(16));
        $this->createGuestCart($token, $productId, $slug, $quantity, $unitPrice);

        // CartService retrouve le panier d'un visiteur par le jeton rangé dans sa session
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart_session_token', $token);
        $request = new Request();
        $request->setSession($session);
        static::getContainer()->get(RequestStack::class)->push($request);
    }

    /** @return list<CartLine> */
    private function linesFor(string $productId): array
    {
        $this->entityManager()->clear();

        return $this->entityManager()->getRepository(CartLine::class)->findBy(['productId' => $productId]);
    }

    private function cartService(): CartService
    {
        return static::getContainer()->get(CartService::class);
    }
}
