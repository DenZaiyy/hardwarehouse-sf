<?php

declare(strict_types=1);

namespace App\Tests\Controller\User\Profile;

use App\Tests\Support\CreatesShopEntities;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProfileControllerTest extends WebTestCase
{
    use CreatesShopEntities;

    public function testProfileWithoutAvatarDoesNotLoadAnExternalImage(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());

        $client->request('GET', '/fr/profile');

        self::assertResponseIsSuccessful();
        // La CSP (img-src) bloque les images d'autres domaines : l'avatar par défaut reste local
        self::assertSelectorNotExists('#user-avatar img[src^="http"]');
    }
}
