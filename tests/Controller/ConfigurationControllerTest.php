<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ConfigurationControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();
        $client->request('GET', '/configuration');
        $client->followRedirect();

        self::assertResponseRedirects();
        self::assertSelectorTextContains('h1', 'Construis ton ordinateur');
    }
}
