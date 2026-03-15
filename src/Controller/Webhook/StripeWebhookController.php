<?php

namespace App\Controller\Webhook;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
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
        $sigHeader = $request->headers->get('stripe-signature');

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
            // Handle the event
            switch ($event['type']) {
                case 'checkout.session.completed':
                    $this->handleCheckoutSessionCompleted($event['data']['object']->toArray());
                    break;

                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event['data']['object']->toArray());
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event['data']['object']->toArray());
                    break;

                case 'payment_intent.canceled':
                    $this->handlePaymentIntentCanceled($event['data']['object']->toArray());
                    break;

                case 'charge.dispute.created':
                    $this->handleChargeDispute($event['data']['object']->toArray());
                    break;

                case 'invoice.payment_succeeded':
                    $this->handleInvoicePaymentSucceeded($event['data']['object']->toArray());
                    break;

                case 'invoice.payment_failed':
                    $this->handleInvoicePaymentFailed($event['data']['object']->toArray());
                    break;

                // Refund events
                case 'charge.refunded':
                    $this->handleChargeRefunded($event['data']['object']->toArray());
                    break;

                case 'payment_intent.amount_capturable_updated':
                    $this->handleAmountCapturableUpdated($event['data']['object']->toArray());
                    break;

                default:
                    $this->logger->info('Unhandled Stripe webhook event', ['type' => $event['type']]);
            }

            return new Response('OK', Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Error processing webhook event', [
                'event_type' => $event['type'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new Response('Internal error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function handleCheckoutSessionCompleted(array $session): void
    {
        $this->logger->info('Checkout session completed', [
            'session_id' => $session['id'],
            'metadata' => $session['metadata'] ?? 'No metadata'
        ]);

        // Find order by session metadata
        if (isset($session['metadata']['order_reference'])) {
            $order = $this->entityManager->getRepository(Order::class)
                ->findOneBy(['reference' => $session['metadata']['order_reference']]);

            if ($order) {
                $order->setStatus(OrderStatus::CONFIRMED);
                $this->entityManager->flush();

                $this->logger->info('Order status updated to CONFIRMED', [
                    'order_id' => $order->getId(),
                    'reference' => $order->getReference()
                ]);
                return;
            }

            $this->logger->warning('Order not found for reference', [
                'order_reference' => $session['metadata']['order_reference']
            ]);
        }

        // Fallback: try to find the most recent pending order
        $recentOrder = $this->entityManager->getRepository(Order::class)
            ->findOneBy(['status' => OrderStatus::PENDING], ['created_at' => 'DESC']);

        if ($recentOrder) {
            $recentOrder->setStatus(OrderStatus::CONFIRMED);
            $this->entityManager->flush();

            $this->logger->info('Recent order status updated to CONFIRMED (fallback)', [
                'order_id' => $recentOrder->getId(),
                'reference' => $recentOrder->getReference()
            ]);
        } else {
            $this->logger->warning('No recent pending orders found for checkout session', [
                'session_id' => $session['id']
            ]);
        }
    }

    private function handlePaymentIntentSucceeded(array $paymentIntent): void
    {
        $this->logger->info('Payment intent succeeded', ['payment_intent_id' => $paymentIntent['id']]);

        // Additional logic if needed
    }

    private function handlePaymentIntentFailed(array $paymentIntent): void
    {
        $this->logger->info('Payment intent failed', ['payment_intent_id' => $paymentIntent['id']]);

        // Find and update order status to FAILED if needed
        if (isset($paymentIntent['metadata']['order_reference'])) {
            $order = $this->entityManager->getRepository(Order::class)
                ->findOneBy(['reference' => $paymentIntent['metadata']['order_reference']]);

            if ($order) {
                $order->setStatus(OrderStatus::CANCELLED);
                $this->entityManager->flush();

                $this->logger->info('Order status updated to CANCELLED due to payment failure', [
                    'order_id' => $order->getId(),
                    'reference' => $order->getReference()
                ]);
            }
        }
    }

    private function handlePaymentIntentCanceled(array $paymentIntent): void
    {
        $this->logger->info('Payment intent canceled', ['payment_intent_id' => $paymentIntent['id']]);

        $this->updateOrderStatusByPaymentIntent($paymentIntent, OrderStatus::CANCELLED, 'Payment canceled');
    }

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

    private function handleChargeRefunded(array $charge): void
    {
        $this->logger->info('Charge refunded', [
            'charge_id' => $charge['id'],
            'amount_refunded' => $charge['amount_refunded'] ?? 0,
            'amount' => $charge['amount'] ?? 0
        ]);

        $amountRefunded = $charge['amount_refunded'] ?? 0;
        $totalAmount = $charge['amount'] ?? 0;
        $isFullRefund = $amountRefunded >= $totalAmount;

        // Find order by payment intent metadata
        $paymentIntentId = $charge['payment_intent'] ?? null;
        if ($paymentIntentId) {
            $this->findAndUpdateOrderByPaymentIntent($paymentIntentId, $isFullRefund);
        }
    }

    private function handleInvoicePaymentSucceeded(array $invoice): void
    {
        $this->logger->info('Invoice payment succeeded', ['invoice_id' => $invoice['id']]);
        // Handle subscription payments if applicable
    }

    private function handleInvoicePaymentFailed(array $invoice): void
    {
        $this->logger->info('Invoice payment failed', ['invoice_id' => $invoice['id']]);
        // Handle subscription payment failures if applicable
    }

    private function handleAmountCapturableUpdated(array $paymentIntent): void
    {
        $this->logger->info('Payment intent amount capturable updated', [
            'payment_intent_id' => $paymentIntent['id'],
            'amount_capturable' => $paymentIntent['amount_capturable'] ?? 0
        ]);
    }

    private function updateOrderStatusByPaymentIntent(array $paymentIntent, OrderStatus $status, string $reason): void
    {
        if (isset($paymentIntent['metadata']['order_reference'])) {
            $order = $this->entityManager->getRepository(Order::class)
                ->findOneBy(['reference' => $paymentIntent['metadata']['order_reference']]);

            if ($order) {
                $order->setStatus($status);
                $this->entityManager->flush();

                $this->logger->info('Order status updated', [
                    'order_id' => $order->getId(),
                    'reference' => $order->getReference(),
                    'new_status' => $status->value,
                    'reason' => $reason
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
                'payment_intent_id' => $paymentIntentId
            ]);
        } else {
            $this->logger->warning('No order found for refund', [
                'payment_intent_id' => $paymentIntentId
            ]);
        }
    }
}
