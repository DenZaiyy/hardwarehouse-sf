<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Carrier;
use App\Entity\Shipment;
use App\Enum\ShipmentStatus;
use App\Tests\Support\CreatesShopEntities;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Chaque écran du back-office s'ouvre pour un administrateur : un champ déduit du mapping qu'EasyAdmin
 * ne sait pas afficher (un enum, par exemple) fait échouer toute la liste.
 */
final class AdminPagesTest extends WebTestCase
{
    use CreatesShopEntities;

    /** @return iterable<string, array{string}> */
    public static function adminPages(): iterable
    {
        foreach (['', '/address', '/carrier', '/cart', '/cart-line', '/invoice', '/order', '/order-line', '/shipment', '/user'] as $path) {
            yield '/fr/admin'.$path => ['/fr/admin'.$path];
        }
    }

    #[DataProvider('adminPages')]
    public function testPageOpensForAnAdministrator(string $url): void
    {
        $client = static::createClient();
        $this->createRowsWithEnums();
        $client->loginUser($this->createUser('admin')->setRoles(['ROLE_SUPER_ADMIN']));
        $this->entityManager()->flush();

        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
    }

    /** Un enum ne fait échouer une liste qu'à l'affichage d'une ligne : chaque écran en reçoit une. */
    private function createRowsWithEnums(): void
    {
        $customer = $this->createUser();
        $this->createAddress($customer);
        $order = $this->withAddresses($this->createOrder($customer));

        $carrier = (new Carrier())->setName('Colissimo')->setPrice('4.90');
        $this->entityManager()->persist($carrier);
        $this->entityManager()->persist((new Shipment())
            ->setTrackingNumber('6A00000000001')
            ->setCarrier($carrier)
            ->setOrder($order)
            ->setStatus(ShipmentStatus::IN_TRANSIT)
            ->setCreatedAt(new \DateTimeImmutable()));
        $this->entityManager()->flush();
    }
}
