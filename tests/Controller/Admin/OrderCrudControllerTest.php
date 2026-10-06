<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Tests\Support\CreatesShopEntities;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le back-office des commandes affichait les champs déduits du mapping : le statut et le moyen de
 * paiement, des enums, n'étaient pas convertibles en texte et la liste échouait.
 */
final class OrderCrudControllerTest extends WebTestCase
{
    use CreatesShopEntities;

    private KernelBrowser $client;
    private Order $order;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->order = $this->withAddresses($this->createOrder($this->createUser())->setStatus(OrderStatus::CONFIRMED));
        $this->client->loginUser($this->createUser('admin')->setRoles(['ROLE_ADMIN']));
        $this->entityManager()->flush();
    }

    public function testAdministratorSeesTheOrders(): void
    {
        $page = $this->client->request('GET', '/fr/admin/order');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString((string) $this->order->getReference(), $page->text());
        self::assertStringContainsString('Confirmé', $page->text());
    }

    public function testAdministratorOpensAnOrder(): void
    {
        $page = $this->client->request('GET', '/fr/admin/order/'.$this->order->getId());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Carte bancaire', $page->text());
    }
}
