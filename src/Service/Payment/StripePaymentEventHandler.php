<?php

namespace App\Service\Payment;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Event\OrderConfirmedEvent;
use App\Repository\OrderRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Applies Stripe webhook events to orders.
 *
 * Since Stripe API 2022-08-01, a Checkout Session is created without a PaymentIntent: the order is
 * found by the reference sent in the session and PaymentIntent metadata, and the PaymentIntent id is
 * recorded on the first event that carries it. Refunds and disputes only carry the PaymentIntent id.
 *
 * Statuses only move forward: a late failure or cancellation never undoes a paid order, and a failed
 * attempt leaves the order pending, since the customer can try again until the session expires.
 *
 * Stripe often sends payment_intent.succeeded and checkout.session.completed within the same second.
 * Each event is therefore applied in a transaction, on the order row locked and read again: the second
 * event waits for the first one and decides on what it wrote. The follow-ups of a confirmation (email,
 * invoice, stock exit) only start once that transaction is committed.
 */
final readonly class StripePaymentEventHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function confirmPayment(string $eventId, ?string $orderReference, ?string $paymentIntentId): void
    {
        $confirmed = $this->entityManager->wrapInTransaction(function () use ($eventId, $orderReference, $paymentIntentId): ?Order {
            $order = $this->resolveOrder($eventId, $orderReference, $paymentIntentId);
            if (null === $order) {
                return null;
            }

            if (OrderStatus::PENDING !== $order->getStatus()) {
                $this->skip($order, $eventId, 'payment confirmed for an order that is no longer pending');

                return null;
            }

            $this->apply($order, $eventId, OrderStatus::CONFIRMED, 'payment succeeded');

            return $order;
        });

        if (!$confirmed instanceof Order) {
            return;
        }

        // La confirmation est validée et le verrou rendu : une suite du paiement en échec (e-mail,
        // facture, stock) ne doit pas faire répondre une erreur, Stripe ignorerait sa reprise
        try {
            $this->eventDispatcher->dispatch(new OrderConfirmedEvent($confirmed));
        } catch (\Throwable $e) {
            $this->logger->critical('A follow-up of a confirmed order failed', [
                'reference' => $confirmed->getReference(),
                'exception' => $e,
            ]);
        }
    }

    /**
     * Abandoned session or canceled PaymentIntent: only a pending order is cancelled.
     */
    public function cancelPendingOrder(string $eventId, ?string $orderReference, ?string $paymentIntentId, string $reason): void
    {
        $this->entityManager->wrapInTransaction(function () use ($eventId, $orderReference, $paymentIntentId, $reason): void {
            $order = $this->resolveOrder($eventId, $orderReference, $paymentIntentId);
            if (null === $order) {
                return;
            }

            if (OrderStatus::PENDING !== $order->getStatus()) {
                $this->skip($order, $eventId, $reason.' after the order left the pending status');

                return;
            }

            $this->apply($order, $eventId, OrderStatus::CANCELLED, $reason);
        });
    }

    public function recordFailedAttempt(string $eventId, ?string $orderReference, ?string $paymentIntentId): void
    {
        $this->entityManager->wrapInTransaction(function () use ($eventId, $orderReference, $paymentIntentId): void {
            $order = $this->resolveOrder($eventId, $orderReference, $paymentIntentId);
            if (null === $order) {
                return;
            }

            $this->skip($order, $eventId, 'payment attempt failed, the customer can try again');
        });
    }

    public function recordRefund(string $paymentIntentId, string $eventId, bool $isFullRefund): void
    {
        $this->entityManager->wrapInTransaction(function () use ($paymentIntentId, $eventId, $isFullRefund): void {
            $order = $this->resolveOrder($eventId, null, $paymentIntentId);
            if (null === $order) {
                return;
            }

            if (!$isFullRefund || OrderStatus::CANCELLED === $order->getStatus()) {
                $this->skip($order, $eventId, $isFullRefund ? 'full refund of a cancelled order' : 'partial refund, status unchanged');

                return;
            }

            $this->apply($order, $eventId, OrderStatus::CANCELLED, 'full refund');
        });
    }

    public function recordDispute(string $paymentIntentId, string $eventId): void
    {
        $this->entityManager->wrapInTransaction(function () use ($paymentIntentId, $eventId): void {
            $order = $this->resolveOrder($eventId, null, $paymentIntentId);
            if (null === $order) {
                return;
            }

            $this->logger->critical('Stripe dispute opened on order', [
                'order_id' => $order->getId(),
                'reference' => $order->getReference(),
                'stripe_event_id' => $eventId,
            ]);

            $order->setLastStripeEventId($eventId);
            $this->entityManager->flush();
        });
    }

    private function apply(Order $order, string $eventId, OrderStatus $status, string $reason): void
    {
        $order->setStatus($status);
        $order->setLastStripeEventId($eventId);
        $this->entityManager->flush();

        $this->logger->info('Order status updated from Stripe webhook', [
            'order_id' => $order->getId(),
            'reference' => $order->getReference(),
            'new_status' => $status->value,
            'reason' => $reason,
            'stripe_event_id' => $eventId,
        ]);
    }

    /** Records the event (and a PaymentIntent id attached on the way) without changing the status. */
    private function skip(Order $order, string $eventId, string $reason): void
    {
        $order->setLastStripeEventId($eventId);
        $this->entityManager->flush();

        $this->logger->info('Stripe event recorded without status change', [
            'order_id' => $order->getId(),
            'reference' => $order->getReference(),
            'status' => $order->getStatus()->value,
            'reason' => $reason,
            'stripe_event_id' => $eventId,
        ]);
    }

    /**
     * Called inside the transaction of the event: the order row stays locked until it ends.
     *
     * @return Order|null the order to update, or null if there is nothing to do (unknown order,
     *                    event already processed, or a PaymentIntent that belongs to another payment)
     */
    private function resolveOrder(string $eventId, ?string $orderReference, ?string $paymentIntentId): ?Order
    {
        $order = null !== $paymentIntentId ? $this->orderRepository->findOneByStripePaymentIntentId($paymentIntentId) : null;
        $order ??= null !== $orderReference ? $this->orderRepository->findOneByReference($orderReference) : null;

        if (null === $order) {
            $this->logger->warning('Stripe event matches no order', [
                'order_reference' => $orderReference,
                'stripe_payment_intent_id' => $paymentIntentId,
                'stripe_event_id' => $eventId,
            ]);

            return null;
        }

        // Verrou posé sur la seule table de la commande (SELECT 1 … FOR UPDATE), puis relecture : un refresh
        // verrouillé ajouterait FOR UPDATE à la jointure externe de la facture, ce que PostgreSQL refuse.
        // Toutes les décisions qui suivent portent ainsi sur la ligne telle qu'elle est en base.
        $this->entityManager->lock($order, LockMode::PESSIMISTIC_WRITE);
        $this->entityManager->refresh($order);

        if ($order->getLastStripeEventId() === $eventId) {
            $this->logger->info('Stripe event already processed for this order, skipping', [
                'order_id' => $order->getId(),
                'stripe_event_id' => $eventId,
            ]);

            return null;
        }

        if (null !== $paymentIntentId) {
            $recorded = $order->getStripePaymentIntentId();

            if (null === $recorded) {
                $order->setStripePaymentIntentId($paymentIntentId);
            } elseif ($recorded !== $paymentIntentId) {
                $this->logger->critical('Stripe event carries another PaymentIntent than the one recorded on the order', [
                    'order_id' => $order->getId(),
                    'recorded_payment_intent_id' => $recorded,
                    'stripe_payment_intent_id' => $paymentIntentId,
                    'stripe_event_id' => $eventId,
                ]);

                return null;
            }
        }

        return $order;
    }
}
