<?php

namespace App\Controller\Webhook;

use App\Service\Payment\StripePaymentEventHandler;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Thin adapter: verifies the Stripe signature, extracts the PaymentIntent id relevant to
 * each event type, and delegates the actual order mutation to StripePaymentEventHandler.
 */
class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly StripePaymentEventHandler $paymentEventHandler,
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
            $event = Webhook::constructEvent($payload, $sigHeader, $this->stripeWebhookSecret);
        } catch (UnexpectedValueException $e) {
            $this->logger->error('Invalid payload in Stripe webhook', ['error' => $e->getMessage()]);

            return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
        } catch (SignatureVerificationException $e) {
            $this->logger->error('Invalid signature in Stripe webhook', ['error' => $e->getMessage()]);

            return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
        }

        $eventId = $event->id;

        try {
            /** @var array<string, mixed> $object */
            $object = $event->data->object->toArray();

            match ($event['type']) {
                'checkout.session.completed' => $this->handleCheckoutSessionCompleted($object, $eventId),
                'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($object, $eventId),
                'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($object, $eventId),
                'payment_intent.canceled' => $this->handlePaymentIntentCanceled($object, $eventId),
                'charge.refunded' => $this->handleChargeRefunded($object, $eventId),
                'charge.dispute.created' => $this->handleChargeDispute($object, $eventId),
                'payment_intent.amount_capturable_updated' => $this->logger->info('Payment intent amount capturable updated', ['payment_intent_id' => $object['id'] ?? null]),
                'invoice.payment_succeeded' => $this->logger->info('Invoice payment succeeded (not used by this store)', ['invoice_id' => $object['id'] ?? null]),
                'invoice.payment_failed' => $this->logger->info('Invoice payment failed (not used by this store)', ['invoice_id' => $object['id'] ?? null]),
                default => $this->logger->info('Unhandled Stripe webhook event', ['type' => $event['type']]),
            };

            return new Response('OK', Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Error processing Stripe webhook event', [
                'event_type' => $event['type'],
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            return new Response('Internal error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** @param array<string, mixed> $session */
    private function handleCheckoutSessionCompleted(array $session, string $eventId): void
    {
        $paymentIntentId = $session['payment_intent'] ?? null;
        if (!is_string($paymentIntentId)) {
            $this->logger->warning('Checkout session completed without a payment_intent', ['session_id' => $session['id'] ?? null]);

            return;
        }

        $this->paymentEventHandler->markOrderPaid($paymentIntentId, $eventId);
    }

    /** @param array<string, mixed> $paymentIntent */
    private function handlePaymentIntentSucceeded(array $paymentIntent, string $eventId): void
    {
        $paymentIntentId = $this->extractPaymentIntentId($paymentIntent);
        if (null !== $paymentIntentId) {
            $this->paymentEventHandler->markOrderPaid($paymentIntentId, $eventId);
        }
    }

    /** @param array<string, mixed> $paymentIntent */
    private function handlePaymentIntentFailed(array $paymentIntent, string $eventId): void
    {
        $paymentIntentId = $this->extractPaymentIntentId($paymentIntent);
        if (null !== $paymentIntentId) {
            $this->paymentEventHandler->markOrderFailed($paymentIntentId, $eventId);
        }
    }

    /** @param array<string, mixed> $paymentIntent */
    private function handlePaymentIntentCanceled(array $paymentIntent, string $eventId): void
    {
        $paymentIntentId = $this->extractPaymentIntentId($paymentIntent);
        if (null !== $paymentIntentId) {
            $this->paymentEventHandler->markOrderCanceled($paymentIntentId, $eventId);
        }
    }

    /** @param array<string, mixed> $paymentIntent */
    private function extractPaymentIntentId(array $paymentIntent): ?string
    {
        $id = $paymentIntent['id'] ?? null;
        if (is_string($id)) {
            return $id;
        }

        $this->logger->warning('Stripe payment_intent event without a valid id', ['payload' => $paymentIntent]);

        return null;
    }

    /** @param array<string, mixed> $charge */
    private function handleChargeRefunded(array $charge, string $eventId): void
    {
        $paymentIntentId = $charge['payment_intent'] ?? null;
        if (!is_string($paymentIntentId)) {
            $this->logger->warning('Charge refunded without a payment_intent', ['charge_id' => $charge['id'] ?? null]);

            return;
        }

        $amountRefunded = $charge['amount_refunded'] ?? 0;
        $totalAmount = $charge['amount'] ?? 0;
        $isFullRefund = (is_int($amountRefunded) ? $amountRefunded : 0) >= (is_int($totalAmount) ? $totalAmount : 0);

        $this->paymentEventHandler->markOrderRefunded($paymentIntentId, $eventId, $isFullRefund);
    }

    /** @param array<string, mixed> $dispute */
    private function handleChargeDispute(array $dispute, string $eventId): void
    {
        $paymentIntentId = $dispute['payment_intent'] ?? null;
        if (!is_string($paymentIntentId)) {
            $this->logger->warning('Dispute created without a payment_intent', ['dispute_id' => $dispute['id'] ?? null]);

            return;
        }

        $this->paymentEventHandler->recordDispute($paymentIntentId, $eventId);
    }
}
