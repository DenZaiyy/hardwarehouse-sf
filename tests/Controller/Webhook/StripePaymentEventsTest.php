<?php

declare(strict_types=1);

namespace App\Tests\Controller\Webhook;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Tests\Support\CreatesShopEntities;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Événements Stripe signés, appliqués à une vraie commande. La session Checkout est créée sans
 * PaymentIntent : la commande se retrouve par la référence transmise dans les métadonnées.
 */
final class StripePaymentEventsTest extends WebTestCase
{
    use CreatesShopEntities;

    // Doit correspondre à STRIPE_WEBHOOK_SECRET dans phpunit.dist.xml
    private const string SECRET = 'whsec_test_hardwarehouse';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testCompletedCheckoutConfirmsTheOrderAndRecordsThePayment(): void
    {
        $order = $this->createOrder($this->createUser());

        $this->send('checkout.session.completed', [
            'object' => 'checkout.session',
            'payment_intent' => 'pi_paid_checkout',
            'payment_status' => 'paid',
            'metadata' => ['order_reference' => $order->getReference()],
        ]);

        $order = $this->reload($order);
        self::assertSame(OrderStatus::CONFIRMED, $order->getStatus());
        self::assertSame('pi_paid_checkout', $order->getStripePaymentIntentId());
    }

    public function testConfirmedOrderSendsTheConfirmationEmailOnce(): void
    {
        $order = $this->createOrder($this->createUser())->setCustomerEmail('jean.dupont@example.com');
        $this->entityManager()->flush();
        $metadata = ['order_reference' => $order->getReference()];

        $this->send('payment_intent.succeeded', ['id' => 'pi_confirmation_email', 'object' => 'payment_intent', 'metadata' => $metadata]);

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'to', 'jean.dupont@example.com');
        self::assertEmailSubjectContains($email, (string) $order->getReference());
        // Commande d'un compte : le lien mène au détail dans l'espace client
        self::assertEmailHtmlBodyContains($email, '/fr/profile/orders/'.$order->getReference());

        // L'événement de session qui suit ne change plus le statut : pas de second e-mail
        $this->send('checkout.session.completed', ['object' => 'checkout.session', 'payment_intent' => 'pi_confirmation_email', 'payment_status' => 'paid', 'metadata' => $metadata]);

        self::assertEmailCount(0);
    }

    public function testConfirmedOrderGetsItsInvoiceAttachedToTheEmail(): void
    {
        $order = $this->createOrder($this->createUser())->setCustomerEmail('jean.dupont@example.com');
        $this->entityManager()->flush();

        $this->send('payment_intent.succeeded', ['id' => 'pi_invoice', 'object' => 'payment_intent', 'metadata' => ['order_reference' => $order->getReference()]]);

        $invoice = $this->reload($order)->getInvoice();
        self::assertNotNull($invoice);
        $email = self::getMailerMessage();
        self::assertInstanceOf(\Symfony\Component\Mime\Email::class, $email);
        $attachments = $email->getAttachments();
        self::assertCount(1, $attachments);
        self::assertSame('facture-'.$invoice->getReference().'.pdf', $attachments[0]->getFilename());
    }

    public function testConfirmedOrderQueuesItsStockExit(): void
    {
        $order = $this->createOrder($this->createUser());

        $this->send('payment_intent.succeeded', ['id' => 'pi_stock_exit', 'object' => 'payment_intent', 'metadata' => ['order_reference' => $order->getReference()]]);

        // Traité par le worker : la réponse au webhook n'attend pas l'API
        /** @var \Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $messages = array_map(static fn ($envelope): object => $envelope->getMessage(), $transport->getSent());
        self::assertEquals([new \App\Message\RecordOrderStockExit((string) $order->getReference())], $messages);
    }

    public function testSucceededPaymentConfirmsTheOrderEvenBeforeTheSessionEvent(): void
    {
        $order = $this->createOrder(null);

        $this->send('payment_intent.succeeded', [
            'id' => 'pi_succeeded_first',
            'object' => 'payment_intent',
            'metadata' => ['order_reference' => $order->getReference()],
        ]);

        $order = $this->reload($order);
        self::assertSame(OrderStatus::CONFIRMED, $order->getStatus());
        self::assertSame('pi_succeeded_first', $order->getStripePaymentIntentId());
    }

    public function testExpiredCheckoutCancelsThePendingOrder(): void
    {
        $order = $this->createOrder(null);

        $this->send('checkout.session.expired', [
            'object' => 'checkout.session',
            'payment_intent' => null,
            'payment_status' => 'unpaid',
            'metadata' => ['order_reference' => $order->getReference()],
        ]);

        self::assertSame(OrderStatus::CANCELLED, $this->reload($order)->getStatus());
    }

    public function testFailedAttemptKeepsTheOrderPendingForAnotherTry(): void
    {
        $order = $this->createOrder(null);

        $this->send('payment_intent.payment_failed', [
            'id' => 'pi_failed_attempt',
            'object' => 'payment_intent',
            'metadata' => ['order_reference' => $order->getReference()],
        ]);

        self::assertSame(OrderStatus::PENDING, $this->reload($order)->getStatus());
    }

    public function testLateCancellationDoesNotUndoAPaidOrder(): void
    {
        $order = $this->paidOrder('pi_already_paid');

        $this->send('payment_intent.canceled', [
            'id' => 'pi_already_paid',
            'object' => 'payment_intent',
            'metadata' => ['order_reference' => $order->getReference()],
        ]);

        self::assertSame(OrderStatus::CONFIRMED, $this->reload($order)->getStatus());
    }

    public function testPartialRefundKeepsTheOrderStatus(): void
    {
        $order = $this->paidOrder('pi_partly_refunded');

        $this->send('charge.refunded', [
            'object' => 'charge',
            'payment_intent' => 'pi_partly_refunded',
            'amount' => 12490,
            'amount_refunded' => 2000,
        ]);

        self::assertSame(OrderStatus::CONFIRMED, $this->reload($order)->getStatus());
    }

    public function testFullRefundCancelsTheOrder(): void
    {
        $order = $this->paidOrder('pi_fully_refunded');

        $this->send('charge.refunded', [
            'object' => 'charge',
            'payment_intent' => 'pi_fully_refunded',
            'amount' => 12490,
            'amount_refunded' => 12490,
        ]);

        self::assertSame(OrderStatus::CANCELLED, $this->reload($order)->getStatus());
    }

    private function paidOrder(string $paymentIntentId): Order
    {
        $order = $this->createOrder($this->createUser())
            ->setStatus(OrderStatus::CONFIRMED)
            ->setStripePaymentIntentId($paymentIntentId);
        $this->entityManager()->flush();

        return $order;
    }

    /** @param array<string, mixed> $object */
    private function send(string $type, array $object): void
    {
        $payload = json_encode([
            'id' => 'evt_'.bin2hex(random_bytes(6)),
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => $object + ['id' => 'obj_'.bin2hex(random_bytes(6))]],
        ], JSON_THROW_ON_ERROR);
        $timestamp = time();

        $this->client->request('POST', '/webhook/stripe', server: [
            'HTTP_STRIPE_SIGNATURE' => \sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', $timestamp.'.'.$payload, self::SECRET)),
            'CONTENT_TYPE' => 'application/json',
        ], content: $payload);

        self::assertResponseIsSuccessful();
    }

    private function reload(Order $order): Order
    {
        $entityManager = $this->entityManager();
        $entityManager->clear();
        $fresh = $entityManager->getRepository(Order::class)->find($order->getId());
        self::assertInstanceOf(Order::class, $fresh);

        return $fresh;
    }
}
