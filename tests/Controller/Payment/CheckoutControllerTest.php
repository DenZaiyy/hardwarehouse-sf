<?php

declare(strict_types=1);

namespace App\Tests\Controller\Payment;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CheckoutControllerTest extends WebTestCase
{
    public function testCheckoutPageTitleIsTranslated(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr/checkout');

        self::assertPageTitleSame('Commande - HardWareHouse');
    }
}
