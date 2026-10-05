<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Address;
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
}
