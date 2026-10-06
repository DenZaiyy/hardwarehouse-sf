<?php

declare(strict_types=1);

namespace App\Tests\Controller\User\Profile;

use App\Entity\Address;
use App\Tests\Support\CreatesShopEntities;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AddressControllerTest extends WebTestCase
{
    use CreatesShopEntities;

    public function testOwnerCanOpenTheEditForm(): void
    {
        $client = static::createClient();
        $owner = $this->createUser('owner');
        $address = $this->createAddress($owner);

        $client->loginUser($owner);
        $client->request('GET', sprintf('/fr/profile/address/%d/edit', $address->getId()));

        self::assertResponseIsSuccessful();
    }

    public function testAnotherCustomerCannotOpenTheEditForm(): void
    {
        $client = static::createClient();
        $address = $this->createAddress($this->createUser('owner'));

        $client->loginUser($this->createUser('intruder'));
        $client->request('GET', sprintf('/fr/profile/address/%d/edit', $address->getId()));

        // 404 plutôt que 403 : l'existence de l'adresse d'un autre client n'est pas révélée
        self::assertResponseStatusCodeSame(404);
    }

    public function testAnotherCustomerCannotDeleteTheAddress(): void
    {
        $client = static::createClient();
        $address = $this->createAddress($this->createUser('owner'));
        $addressId = $address->getId();

        $client->loginUser($this->createUser('intruder'));
        $client->request('POST', sprintf('/fr/profile/address/%d/delete', $addressId));

        self::assertResponseStatusCodeSame(404);
        $this->entityManager()->clear();
        self::assertNotNull($this->entityManager()->find(Address::class, $addressId));
    }
}
