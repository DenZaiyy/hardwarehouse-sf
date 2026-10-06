<?php

declare(strict_types=1);

namespace App\Tests\Controller\User\Profile;

use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Tests\Support\CreatesShopEntities;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * « Mes commandes » : chaque client ne voit que ses commandes, et le détail d'une commande d'un autre
 * client est introuvable plutôt qu'interdit (son existence n'est pas révélée).
 */
final class OrderControllerTest extends WebTestCase
{
    use CreatesShopEntities;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testCustomerSeesOnlyTheirOwnOrders(): void
    {
        $customer = $this->createUser();
        $own = $this->orderWithLine($customer, quantity: 2);
        $other = $this->orderWithLine($this->createUser('autre'), quantity: 1);

        $this->client->loginUser($customer);
        $page = $this->client->request('GET', '/fr/profile/orders');

        self::assertResponseIsSuccessful();
        $rows = $page->filter('table tbody tr');
        self::assertCount(1, $rows);
        self::assertStringContainsString((string) $own->getReference(), $rows->text());
        self::assertStringContainsString('Confirmé', $rows->text());
        self::assertStringNotContainsString((string) $other->getReference(), $page->text());
    }

    public function testOrderDetailShowsItsLinesAndTotal(): void
    {
        $customer = $this->createUser();
        $order = $this->orderWithLine($customer, quantity: 2);

        $this->client->loginUser($customer);
        $page = $this->client->request('GET', '/fr/profile/orders/'.$order->getReference());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('AMD Ryzen 7 7800X3D', $page->text());
        self::assertStringContainsString('124,90', $page->text());
    }

    public function testOrderOfAnotherCustomerIsNotFound(): void
    {
        $other = $this->orderWithLine($this->createUser('autre'), quantity: 1);

        $this->client->loginUser($this->createUser());
        $this->client->request('GET', '/fr/profile/orders/'.$other->getReference());

        self::assertResponseStatusCodeSame(404);
    }

    public function testVisitorIsAskedToLogIn(): void
    {
        $this->client->request('GET', '/fr/profile/orders');

        self::assertResponseRedirects();
        self::assertStringContainsString('/connexion', (string) $this->client->getResponse()->headers->get('Location'));
    }

    /** Commande confirmée de 124,90 € TTC, port compris. */
    private function orderWithLine(User $customer, int $quantity): Order
    {
        $order = $this->createOrder($customer)->setStatus(OrderStatus::CONFIRMED);
        $order->addOrderLine((new OrderLine())
            ->setProductId('produit-test')
            ->setProductName('AMD Ryzen 7 7800X3D')
            ->setProductSlug('amd-ryzen-7-7800x3d')
            ->setQuantity($quantity)
            ->setUnitPrice('50.00')
            ->setTaxRate('0.2')
            ->setLineTotal('120.00'));
        $this->entityManager()->flush();

        return $order;
    }
}
