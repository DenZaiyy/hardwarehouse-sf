<?php

declare(strict_types=1);

namespace App\Tests\Controller\Payment;

use App\Service\Checkout\CheckoutStateManager;
use App\Tests\Support\CreatesShopEntities;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;

final class PaymentControllerTest extends WebTestCase
{
    use CreatesShopEntities;

    public function testCustomerSeesTheConfirmationOfTheirOwnOrder(): void
    {
        $client = static::createClient();
        $customer = $this->createUser('customer');
        $order = $this->createOrder($customer);

        $client->loginUser($customer);
        $client->request('GET', '/fr/payment/success/'.$order->getReference());

        self::assertResponseIsSuccessful();
    }

    public function testAnotherCustomerCannotSeeTheConfirmation(): void
    {
        $client = static::createClient();
        $order = $this->createOrder($this->createUser('customer'));

        $client->loginUser($this->createUser('intruder'));
        $client->request('GET', '/fr/payment/success/'.$order->getReference());

        self::assertResponseStatusCodeSame(404);
    }

    public function testVisitorCannotSeeTheConfirmationOfAGuestOrderPlacedElsewhere(): void
    {
        $client = static::createClient();
        $order = $this->createOrder(null);

        $client->request('GET', '/fr/payment/success/'.$order->getReference());

        self::assertResponseStatusCodeSame(404);
    }

    public function testGuestSeesTheConfirmationOfTheOrderPlacedInTheirSession(): void
    {
        $client = static::createClient();
        $order = $this->createOrder(null);

        $this->rememberInSession($client, CheckoutStateManager::ORDER_REFERENCE_KEY, (string) $order->getReference());
        $client->request('GET', '/fr/payment/success/'.$order->getReference());

        self::assertResponseIsSuccessful();
    }

    private function rememberInSession(KernelBrowser $client, string $key, string $value): void
    {
        /** @var SessionFactoryInterface $factory */
        $factory = static::getContainer()->get('session.factory');
        $session = $factory->createSession();
        $session->set($key, $value);
        $session->save();

        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
    }
}
