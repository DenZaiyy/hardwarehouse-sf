<?php

namespace App\Controller\Webhook;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\StripeObject;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(STRIPE_WEBHOOK_SECRET)')]
        private readonly string $stripeWebhookSecret,
    ) {
    }

    #[Route('/webhook/stripe', name: 'webhook.stripe', methods: ['POST'])]
    public function handleStripeWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature') ?? '';

        try {
            // Verify webhook signature
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $this->stripeWebhookSecret
            );
        } catch (UnexpectedValueException $e) {
            $this->logger->error('Invalid payload in Stripe webhook', ['error' => $e->getMessage()]);

            return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
        } catch (SignatureVerificationException $e) {
            $this->logger->error('Invalid signature in Stripe webhook', ['error' => $e->getMessage()]);

            return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
        }

        try {
            /** @var StripeObject $dataObject */
            $dataObject = $event->data->object;

            /** @var array<string, mixed> $objectArray */
            $objectArray = $dataObject->toArray();

            // Handle the event
            match ($event['type']) {
                'checkout.session.completed' => $this->handleCheckoutSessionCompleted($objectArray),
                'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($objectArray),
                'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($objectArray),
                'payment_intent.canceled' => $this->handlePaymentIntentCanceled($objectArray),
                'charge.dispute.created' => $this->handleChargeDispute($objectArray),
                'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($objectArray),
                'invoice.payment_failed' => $this->handleInvoicePaymentFailed($objectArray),
                'charge.refunded' => $this->handleChargeRefunded($objectArray),
                'payment_intent.amount_capturable_updated' => $this->handleAmountCapturableUpdated($objectArray),
                default => $this->logger->info('Unhandled Stripe webhook event', ['type' => $event['type']]),
            };

            return new Response('OK', Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Error processing webhook event', [
                'event_type' => $event['type'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new Response('Internal error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** @param array<string, mixed> $session */
    private function handleCheckoutSessionCompleted(array $session): void
    {
        $this->logger->info('Checkout session completed', [
            'session_id' => $session['id'],
            'metadata' => $session['metadata'] ?? 'No metadata',
        ]);

        $metadata = $session['metadata'] ?? [];
        if (is_array($metadata) && isset($metadata['order_reference']) && is_string($metadata['order_reference'])) {
            $order = $this->entityManager->getRepository(Order::class)
                ->findOneBy(['reference' => $metadata['order_reference']]);

            if ($order) {
                $order->setStatus(OrderStatus::CONFIRMED);
                $this->entityManager->flush();

                $this->logger->info('Order status updated to CONFIRMED', [
                    'order_id' => $order->getId(),
                    'reference' => $order->getReference(),
                ]);

                return;
            }

            $this->logger->warning('Order not found for reference', [
                'order_reference' => $metadata['order_reference'],
            ]);
        }

        $recentOrder = $this->entityManager->getRepository(Order::class)
            ->findOneBy(['status' => OrderStatus::PENDING], ['created_at' => 'DESC']);

        if ($recentOrder) {
            $recentOrder->setStatus(OrderStatus::CONFIRMED);
            $this->entityManager->flush();

            $this->logger->info('Recent order status updated to CONFIRMED (fallback)', [
                'order_id' => $recentOrder->getId(),
                'reference' => $recentOrder->getReference(),
            ]);
        } else {
            $this->logger->warning('No recent pending orders found for checkout session', [
                'session_id' => $session['id'],
            ]);
        }
    }

    /** @param array<string, mixed> $paymentIntent */
    private function handlePaymentIntentSucceeded(array $paymentIntent): void
    {
        $this->logger->info('Payment intent succeeded', ['payment_intent_id' => $paymentIntent['id']]);

        // Additional logic if needed
    }

    /** @param array<string, mixed> $paymentIntent */
    private function handlePaymentIntentFailed(array $paymentIntent): void
    {
        $this->logger->info('Payment intent failed', ['payment_intent_id' => $paymentIntent['id']]);

        $metadata = $paymentIntent['metadata'] ?? [];
        // Find and update order status to FAILED if needed
        if (is_array($metadata) && isset($metadata['order_reference']) && is_string($metadata['order_reference'])) {
            $order = $this->entityManager->getRepository(Order::class)
                ->findOneBy(['reference' => $metadata['order_reference']]);

            if ($order) {
                $order->setStatus(OrderStatus::CANCELLED);
                $this->entityManager->flush();

                $this->logger->info('Order status updated to CANCELLED due to payment failure', [
                    'order_id' => $order->getId(),
                    'reference' => $order->getReference(),
                ]);
            }
        }
    }

    /** @param array<string, mixed> $paymentIntent */
    private function handlePaymentIntentCanceled(array $paymentIntent): void
    {
        $this->logger->info('Payment intent canceled', ['payment_intent_id' => $paymentIntent['id']]);

        $this->updateOrderStatusByPaymentIntent($paymentIntent, OrderStatus::CANCELLED, 'Payment canceled');
    }

    /** @param array<string, mixed> $dispute */
    private function handleChargeDispute(array $dispute): void
    {
        $this->logger->info('Charge dispute created', ['dispute_id' => $dispute['id']]);

        // Find order by charge ID and update status
        $chargeId = $dispute['charge'] ?? null;
        if ($chargeId) {
            // You might need to store charge ID with order to link them
            $this->logger->info('Dispute created for charge', ['charge_id' => $chargeId]);
            // Add logic to find order by charge ID if needed
        }
    }

    /** @param array<string, mixed> $charge */
    private function handleChargeRefunded(array $charge): void
    {
        $this->logger->info('Charge refunded', [
            'charge_id' => $charge['id'],
            'amount_refunded' => $charge['amount_refunded'] ?? 0,
            'amount' => $charge['amount'] ?? 0,
        ]);

        $amountRefunded = $charge['amount_refunded'];
        $totalAmount = $charge['amount'];

        $amountRefundedInt = is_int($amountRefunded) ? $amountRefunded : 0;
        $totalAmountInt = is_int($totalAmount) ? $totalAmount : 0;
        $isFullRefund = $amountRefundedInt >= $totalAmountInt;

        // Find order by payment intent metadata
        $paymentIntentId = $charge['payment_intent'] ?? null;
        if (is_string($paymentIntentId)) {
            $this->findAndUpdateOrderByPaymentIntent($paymentIntentId, $isFullRefund);
        }
    }

    /** @param array<string, mixed> $invoice */
    private function handleInvoicePaymentSucceeded(array $invoice): void
    {
        $this->logger->info('Invoice payment succeeded', ['invoice_id' => $invoice['id']]);
        // Handle subscription payments if applicable
    }

    /** @param array<string, mixed> $invoice */
    private function handleInvoicePaymentFailed(array $invoice): void
    {
        $this->logger->info('Invoice payment failed', ['invoice_id' => $invoice['id']]);
        // Handle subscription payment failures if applicable
    }

    /** @param array<string, mixed> $paymentIntent */
    private function handleAmountCapturableUpdated(array $paymentIntent): void
    {
        $this->logger->info('Payment intent amount capturable updated', [
            'payment_intent_id' => $paymentIntent['id'],
            'amount_capturable' => $paymentIntent['amount_capturable'] ?? 0,
        ]);
    }

    /** @param array<string, mixed> $paymentIntent */
    private function updateOrderStatusByPaymentIntent(array $paymentIntent, OrderStatus $status, string $reason): void
    {
        $metadata = $paymentIntent['metadata'] ?? [];
        if (is_array($metadata) && isset($metadata['order_reference']) && is_string($metadata['order_reference'])) {
            $order = $this->entityManager->getRepository(Order::class)
                ->findOneBy(['reference' => $metadata['order_reference']]);

            if ($order) {
                $order->setStatus($status);
                $this->entityManager->flush();

                $this->logger->info('Order status updated', [
                    'order_id' => $order->getId(),
                    'reference' => $order->getReference(),
                    'new_status' => $status->value,
                    'reason' => $reason,
                ]);
            }
        }
    }

    private function findAndUpdateOrderByPaymentIntent(string $paymentIntentId, bool $isFullRefund): void
    {
        // For refunds, we need to find the order differently since we don't have direct metadata
        // You might need to store payment_intent_id with orders to make this lookup easier

        // For now, let's find the most recent confirmed order and update it
        $recentOrder = $this->entityManager->getRepository(Order::class)
            ->findOneBy(['status' => OrderStatus::CONFIRMED], ['created_at' => 'DESC']);

        if ($recentOrder) {
            $newStatus = $isFullRefund ? OrderStatus::CANCELLED : OrderStatus::PROCESSING;
            $recentOrder->setStatus($newStatus);
            $this->entityManager->flush();

            $this->logger->info('Order status updated due to refund', [
                'order_id' => $recentOrder->getId(),
                'reference' => $recentOrder->getReference(),
                'new_status' => $newStatus->value,
                'is_full_refund' => $isFullRefund,
                'payment_intent_id' => $paymentIntentId,
            ]);
        } else {
            $this->logger->warning('No order found for refund', [
                'payment_intent_id' => $paymentIntentId,
            ]);
        }
    }
}
