<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Address;
use App\Entity\Cart;
use App\Entity\CartLine;
use App\Entity\Order;
use App\Entity\User;
use App\Enum\AddressType;
use App\Enum\CountryList;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Crée en base de test les entités dont les tests fonctionnels ont besoin.
 * Les identifiants sont aléatoires : les tests peuvent être relancés sans recréer la base.
 */
trait CreatesShopEntities
{
    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createUser(string $prefix = 'client'): User
    {
        $suffix = bin2hex(random_bytes(4));
        $user = (new User())
            ->setEmail(sprintf('%s-%s@example.com', $prefix, $suffix))
            ->setUsername($prefix.$suffix)
            ->setPassword('unused-in-functional-tests')
            ->setIsVerified(true);

        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }

    private function createAddress(User $owner): Address
    {
        $address = (new Address())
            ->setLabel('Domicile')
            ->setFirstName('Jean')
            ->setLastName('Dupont')
            ->setAddress('12 rue des Essais')
            ->setPostalCode('68100')
            ->setCity('Mulhouse')
            ->setCountry(CountryList::FR)
            ->setIsDefault(false)
            ->setType(AddressType::DELIVERY)
            ->setUser($owner)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager()->persist($address);
        $this->entityManager()->flush();

        return $address;
    }

    /**
     * Panier d'un visiteur (retrouvé par le jeton de session) contenant une ligne pour ce produit,
     * avec les instantanés d'un ajout au panier passé : nom, prix et stock au moment de l'ajout.
     */
    private function createGuestCart(string $sessionToken, string $productId, string $slug, int $quantity, string $unitPrice): Cart
    {
        $cart = (new Cart())->setSessionToken($sessionToken);
        $line = (new CartLine())
            ->setProductId($productId)
            ->setQuantity($quantity)
            ->setUnitPriceSnapshot($unitPrice)
            ->setProductNameSnapshot('AMD Ryzen 7 7800X3D')
            ->setProductSlugSnapshot($slug)
            ->setProductImageSnapshot('https://example.com/ryzen-7-7800x3d.webp')
            ->setProductCategorySnapshot('Processeurs')
            ->setStockSnapshot(10)
            ->setCart($cart);
        $cart->addCartLine($line);

        $this->entityManager()->persist($cart);
        $this->entityManager()->persist($line);
        $this->entityManager()->flush();

        return $cart;
    }

    /** Commande en attente de paiement ; $customer null pour une commande passée sans compte. */
    private function createOrder(?User $customer): Order
    {
        $order = (new Order())
            ->setReference('ORDTEST'.strtoupper(bin2hex(random_bytes(5))))
            ->setUser($customer)
            ->setUserFullNameSnapshot('Jean Dupont')
            ->setSubtotal('100.00')
            ->setShippingAmount('4.90')
            ->setDiscountAmount('0.00')
            ->setTaxAmount('20.00')
            ->setTotalAmount('124.90')
            ->setCurrency('EUR');

        $this->entityManager()->persist($order);
        $this->entityManager()->flush();

        return $order;
    }
}
