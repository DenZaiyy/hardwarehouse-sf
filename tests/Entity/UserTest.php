<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * À chaque requête, Symfony compare l'utilisateur gardé en session à celui relu en base (User::isEqualTo()) :
 * s'ils diffèrent, la session est fermée.
 */
final class UserTest extends TestCase
{
    public function testSessionStaysOpenWhileTheUserIsUnchanged(): void
    {
        $user = self::user();

        self::assertTrue(self::sessionCopy($user)->isEqualTo($user));
    }

    public function testPasswordChangeEndsTheSession(): void
    {
        $user = self::user();
        $session = self::sessionCopy($user);

        $user->setPassword('$2y$04$autre-hachage-du-nouveau-mot-de-passe');

        self::assertFalse($session->isEqualTo($user));
    }

    public function testRoleChangeEndsTheSession(): void
    {
        $user = self::user();
        $session = self::sessionCopy($user);

        $user->setRoles(['ROLE_ADMIN']);

        self::assertFalse($session->isEqualTo($user));
    }

    private static function user(): User
    {
        return (new User())
            ->setEmail('client@example.com')
            ->setPassword('$2y$04$hachage-du-mot-de-passe-initial');
    }

    /**
     * Ce que la session garde de l'utilisateur : User::__serialize() y remplace le hachage par son crc32c.
     */
    private static function sessionCopy(User $user): User
    {
        $copy = unserialize(serialize($user));
        self::assertInstanceOf(User::class, $copy);

        return $copy;
    }
}
