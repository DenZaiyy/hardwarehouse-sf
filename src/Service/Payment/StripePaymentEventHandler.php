<?php

namespace App\Service\Payment;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Applies Stripe webhook events to orders.
 *
 * Every mutation is resolved strictly via Order::stripePaymentIntentId, set right after
 * the Checkout Session is created (see CheckoutComponent::processPayment()). There is no
 * fallback onto "the most recent order": if a PaymentIntent id doesn't match any order,
 * nothing is mutated — the event is logged and dropped.
 */
final readonly class StripePaymentEventHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function markOrderPaid(string $paymentIntentId, string $eventId): void
    {
        $this->transition($paymentIntentId, $eventId, OrderStatus::CONFIRMED, 'payment succeeded');
    }

    public function markOrderFailed(string $paymentIntentId, string $eventId): void
    {
        $this->transition($paymentIntentId, $eventId, OrderStatus::CANCELLED, 'payment failed');
    }

    public function markOrderCanceled(string $paymentIntentId, string $eventId): void
    {
        $this->transition($paymentIntentId, $eventId, OrderStatus::CANCELLED, 'payment canceled');
    }

    public function markOrderRefunded(string $paymentIntentId, string $eventId, bool $isFullRefund): void
    {
        $status = $isFullRefund ? OrderStatus::CANCELLED : OrderStatus::PROCESSING;
        $this->transition($paymentIntentId, $eventId, $status, $isFullRefund ? 'full refund' : 'partial refund');
    }

    public function recordDispute(string $paymentIntentId, string $eventId): void
    {
        $order = $this->resolveOrder($paymentIntentId, $eventId);
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
    }

    private function transition(string $paymentIntentId, string $eventId, OrderStatus $status, string $reason): void
    {
        $order = $this->resolveOrder($paymentIntentId, $eventId);
        if (null === $order) {
            return;
        }

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

    /**
     * @return Order|null the order to mutate, or null if there is nothing to do
     *                     (unknown PaymentIntent, or event already processed)
     */
    private function resolveOrder(string $paymentIntentId, string $eventId): ?Order
    {
        $order = $this->orderRepository->findOneByStripePaymentIntentId($paymentIntentId);

        if (null === $order) {
            $this->logger->warning('Stripe event references an unknown PaymentIntent, no order matched', [
                'stripe_payment_intent_id' => $paymentIntentId,
                'stripe_event_id' => $eventId,
            ]);

            return null;
        }

        if ($order->getLastStripeEventId() === $eventId) {
            $this->logger->info('Stripe event already processed for this order, skipping', [
                'order_id' => $order->getId(),
                'stripe_event_id' => $eventId,
            ]);

            return null;
        }

        return $order;
    }
}
