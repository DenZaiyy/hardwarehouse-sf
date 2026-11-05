<?php

declare(strict_types=1);

namespace App\Tests\Service\Payment;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Event\OrderConfirmedEvent;
use App\Service\Payment\StripePaymentEventHandler;
use App\Tests\Support\CreatesShopEntities;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Stripe envoie souvent payment_intent.succeeded et checkout.session.completed dans la même seconde.
 * Traités en parallèle, les deux événements lisaient la commande en attente et la confirmaient chacun :
 * deux e-mails de confirmation. Chaque événement relit désormais la commande sous verrou avant de décider.
 */
final class StripePaymentEventHandlerTest extends KernelTestCase
{
    use CreatesShopEntities;

    public function testAnOrderConfirmedByAConcurrentEventIsNotConfirmedTwice(): void
    {
        $order = $this->confirmableOrder();
        // L'autre événement a confirmé la commande entre-temps : la ligne a changé en base, pas la copie
        // que Doctrine garde en mémoire, comme dans une requête qui l'a lue juste avant
        $this->entityManager()->createQuery('UPDATE '.Order::class.' o SET o.status = :status WHERE o.id = :id')
            ->setParameter('status', OrderStatus::CONFIRMED->value)
            ->setParameter('id', $order->getId())
            ->execute();
        $confirmations = $this->recordConfirmations();

        $this->handler()->confirmPayment('evt_second_event', $order->getReference(), 'pi_concurrent_events');

        self::assertCount(0, $confirmations, 'Une commande déjà confirmée ne repart pas dans les suites du paiement.');
        self::assertSame(OrderStatus::CONFIRMED, $order->getStatus());
    }

    public function testConfirmationFollowUpsRunOnceTheOrderIsCommitted(): void
    {
        $order = $this->confirmableOrder();
        // DAMA ouvre une transaction autour de chaque test : le niveau de départ n'est pas 0
        $levelBefore = $this->entityManager()->getConnection()->getTransactionNestingLevel();
        $confirmations = $this->recordConfirmations();

        $this->handler()->confirmPayment('evt_paid', $order->getReference(), 'pi_paid_once');

        // E-mail, facture et sortie de stock ne partent jamais tant que le verrou est tenu
        self::assertSame([$levelBefore], $confirmations->getArrayCopy());
    }

    private function confirmableOrder(): Order
    {
        return $this->withAddresses($this->createOrder($this->createUser())->setCustomerEmail('jean.dupont@example.com'));
    }

    /**
     * Niveau de transaction au moment de chaque OrderConfirmedEvent. Écouté avant les autres écouteurs :
     * une erreur de l'un d'eux arrêterait la propagation avant l'enregistrement.
     *
     * @return \ArrayObject<int, int>
     */
    private function recordConfirmations(): \ArrayObject
    {
        /** @var \ArrayObject<int, int> $levels */
        $levels = new \ArrayObject();
        $connection = $this->entityManager()->getConnection();
        $dispatcher = static::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $dispatcher->addListener(OrderConfirmedEvent::class, static function () use ($levels, $connection): void {
            $levels[] = $connection->getTransactionNestingLevel();
        }, 1000);

        return $levels;
    }

    private function handler(): StripePaymentEventHandler
    {
        $handler = static::getContainer()->get(StripePaymentEventHandler::class);
        self::assertInstanceOf(StripePaymentEventHandler::class, $handler);

        return $handler;
    }
}
