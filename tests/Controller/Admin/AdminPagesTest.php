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
        foreach (['', '/address', '/carrier', '/cart', '/cart-line', '/invoice', '/order', '/order-line', '/rating', '/shipment', '/user'] as $path) {
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

    /**
     * Le filtre Utilisateur des adresses suggère les comptes par autocomplétion. EasyAdmin 5.10.4 vérifie désormais
     * la page et le champ d'où vient la demande : ce test passe par l'URL que le formulaire de filtres fournit.
     */
    public function testAddressUserFilterSuggestsCustomers(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser('admin')->setRoles(['ROLE_SUPER_ADMIN']));
        $customer = $this->createUser('cliente');
        $this->entityManager()->flush();

        $filters = $client->request('GET', '/fr/admin/address/render-filters');
        $endpoint = $filters->filter('[data-ea-autocomplete-endpoint-url]')->attr('data-ea-autocomplete-endpoint-url');
        self::assertNotNull($endpoint);
        $client->request('GET', $endpoint.'&query='.rawurlencode((string) $customer->getUsername()));

        self::assertResponseIsSuccessful();
        /** @var array{results: list<array{entityId: string}>} $suggestions */
        $suggestions = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertContains((string) $customer->getId(), array_column($suggestions['results'], 'entityId'));
    }

    /**
     * Les factures, les commandes et les comptes ne se créent pas depuis le back-office (actions désactivées).
     *
     * @return iterable<string, array{string}>
     */
    public static function creationForms(): iterable
    {
        foreach (['/address', '/carrier', '/cart', '/cart-line', '/order-line', '/shipment'] as $path) {
            yield '/fr/admin'.$path.'/new' => ['/fr/admin'.$path.'/new'];
        }
    }

    #[DataProvider('creationForms')]
    public function testCreationFormOpensForAnAdministrator(string $url): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser('admin')->setRoles(['ROLE_SUPER_ADMIN']));
        $this->entityManager()->flush();

        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
    }

    /**
     * Un compte se crée par l'inscription ou le tunnel de commande, avec la vérification de l'e-mail et la
     * politique de mot de passe : le back-office n'en gère que les rôles et le bannissement.
     */
    public function testUserAccountsAreNotCreatedFromTheBackOffice(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser('admin')->setRoles(['ROLE_SUPER_ADMIN']));
        $this->entityManager()->flush();

        $client->request('GET', '/fr/admin/user');
        self::assertSelectorNotExists('a[href$="/admin/user/new"]');

        $client->request('GET', '/fr/admin/user/new');
        self::assertResponseStatusCodeSame(403);
    }

    /** Les rôles se modifient ici : roles stocke des chaînes, que le champ doit cocher. */
    public function testUserEditionFormChecksTheRolesOfTheUser(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser('admin')->setRoles(['ROLE_SUPER_ADMIN']));
        $customer = $this->createUser()->setRoles(['ROLE_ADMIN']);
        $this->entityManager()->flush();

        $client->request('GET', '/fr/admin/user/'.$customer->getId().'/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="User[roles][]"][value="ROLE_ADMIN"]:checked');
        self::assertSelectorNotExists('input[name="User[roles][]"][value="ROLE_SUPER_ADMIN"]:checked');
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
