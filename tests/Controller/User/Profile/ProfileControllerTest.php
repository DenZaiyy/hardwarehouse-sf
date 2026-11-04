<?php

declare(strict_types=1);

namespace App\Tests\Controller\User\Profile;

use App\Entity\User;
use App\Tests\Support\CreatesShopEntities;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProfileControllerTest extends WebTestCase
{
    use CreatesShopEntities;

    private const string CURRENT_PASSWORD = 'Ancien-Secret-2026!';
    private const string NEW_PASSWORD = 'Nouveau-Secret-2026!';

    public function testPasswordChangeRequiresTheCurrentPassword(): void
    {
        $client = static::createClient();
        $user = $this->createUserWithPassword();
        $client->loginUser($user);

        $this->submitPasswordChange($client, 'Pas-Le-Bon-Secret-1!');

        // 422 : Turbo réaffiche alors le formulaire avec son erreur
        self::assertResponseStatusCodeSame(422);
        self::assertTrue($this->hasher()->isPasswordValid($this->reload($user), self::CURRENT_PASSWORD));
    }

    public function testPasswordChangeKeepsThisSessionOpen(): void
    {
        $client = static::createClient();
        $user = $this->createUserWithPassword();
        $client->loginUser($user);

        $this->submitPasswordChange($client, self::CURRENT_PASSWORD);
        self::assertResponseRedirects('/fr/profile');
        self::assertTrue($this->hasher()->isPasswordValid($this->reload($user), self::NEW_PASSWORD));

        $client->request('GET', '/fr/profile');
        self::assertResponseIsSuccessful();
    }

    public function testPasswordChangedElsewhereEndsTheSession(): void
    {
        $client = static::createClient();
        $user = $this->createUserWithPassword();
        $client->loginUser($user);
        $client->request('GET', '/fr/profile');
        self::assertResponseIsSuccessful();

        // Changé depuis un autre appareil, par exemple après le vol de cette session
        $stored = $this->reload($user);
        $stored->setPassword($this->hasher()->hashPassword($stored, self::NEW_PASSWORD));
        $this->entityManager()->flush();

        $client->request('GET', '/fr/profile');
        self::assertResponseRedirects();
    }

    public function testProfileWithoutAvatarDoesNotLoadAnExternalImage(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());

        $client->request('GET', '/fr/profile');

        self::assertResponseIsSuccessful();
        // La CSP (img-src) bloque les images d'autres domaines : l'avatar par défaut reste local
        self::assertSelectorNotExists('#user-avatar img[src^="http"]');
    }

    private function submitPasswordChange(KernelBrowser $client, string $currentPassword): void
    {
        $crawler = $client->request('GET', '/fr/profile/password');
        $client->submit($crawler->filter('form[name="change_password_form"]')->form([
            'change_password_form[password]' => $currentPassword,
            'change_password_form[plainPassword][first]' => self::NEW_PASSWORD,
            'change_password_form[plainPassword][second]' => self::NEW_PASSWORD,
        ]));
    }

    private function createUserWithPassword(): User
    {
        $user = $this->createUser();
        $user->setPassword($this->hasher()->hashPassword($user, self::CURRENT_PASSWORD));
        $this->entityManager()->flush();

        return $user;
    }

    /**
     * Relit l'utilisateur en base : le client redémarre le noyau entre deux requêtes, avec un autre gestionnaire d'entités.
     */
    private function reload(User $user): User
    {
        $this->entityManager()->clear();
        $stored = $this->entityManager()->find(User::class, $user->getId());
        self::assertInstanceOf(User::class, $stored);

        return $stored;
    }

    private function hasher(): UserPasswordHasherInterface
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        return $hasher;
    }
}
