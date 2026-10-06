<?php

declare(strict_types=1);

namespace App\Tests\Service\Checkout;

use App\DTO\Checkout\GuestIdentityData;
use App\Entity\User;
use App\Service\Checkout\GuestAccountCreator;
use App\Tests\Support\CreatesShopEntities;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Le pseudonyme d'un compte créé dans le tunnel reprend le nom saisi, unique et de 20 caractères au plus. */
final class GuestAccountCreatorTest extends KernelTestCase
{
    use CreatesShopEntities;

    public function testUsernameTakenGetsANumber(): void
    {
        $this->createUser()->setUsername('Jean Dupont');
        $this->entityManager()->flush();

        self::assertSame('Jean Dupont 2', $this->create('Jean', 'Dupont')->getUsername());
    }

    public function testLongNameIsShortenedToTwentyCharacters(): void
    {
        $username = (string) $this->create('Marie-Bernadette', 'de La Rochefoucauld')->getUsername();

        self::assertSame('Marie-Bernadette de', $username);
        self::assertLessThanOrEqual(20, mb_strlen($username));
    }

    private function create(string $firstName, string $lastName): User
    {
        $identity = new GuestIdentityData();
        $identity->firstName = $firstName;
        $identity->lastName = $lastName;
        $identity->email = 'compte-'.bin2hex(random_bytes(4)).'@example.com';
        $identity->password = 'MotDePasse-2026!';

        return static::getContainer()->get(GuestAccountCreator::class)->create($identity);
    }
}
