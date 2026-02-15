<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ConfigurationControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();
        $client->request('GET', '/fr/configurations');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Construis ton ordinateur');
    }
}
