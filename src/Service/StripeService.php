<?php

namespace App\Service;

use Stripe\Stripe;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

class StripeService
{
    public function __construct(
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private readonly string $stripeSecretKey,
        #[Autowire('%env(STRIPE_WEBHOOK_SECRET)')]
        private readonly string $webhookSecretKey = '',
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
        Stripe::setApiVersion('');
    }

    /**
     * Create Stripe checkout session with cart items and carrier costs
     * @param array{subtotal: float, vat_rate: float, vat_amount: float, carrier_cost: float, total: float} $orderTotals
     * @param array<string, array{productId: string, quantity: int, remaining_stock: int, category: string, name: string, price_ht: float, price_ttc: float, imageUrl: string, slug: string}> $cartItems
     * @param string|null $carrierLabel
     */
    public function createCheckoutSession(array $orderTotals, array $cartItems, ?string $carrierLabel, string $successUrl, string $cancelUrl, ?string $orderReference = null): \Stripe\Checkout\Session
    {
        $lineItems = [];

        // Add cart items
        foreach ($cartItems as $item) {
            $priceTTC = (int) round($item['price_ttc'] * 100); // Convert to cents
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $item['name'],
                        'description' => "Catégorie: {$item['category']}",
                    ],
                    'unit_amount' => $priceTTC,
                ],
                'quantity' => $item['quantity'],
            ];
        }

        // Add carrier cost if exists
        if ($orderTotals['carrier_cost'] > 0 && $carrierLabel) {
            $carrierCostCents = (int) round($orderTotals['carrier_cost'] * 100);
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => 'Frais de livraison',
                        'description' => $carrierLabel,
                    ],
                    'unit_amount' => $carrierCostCents,
                ],
                'quantity' => 1,
            ];
        }

        $metadata = [
            'subtotal_ht' => (string) $orderTotals['subtotal'],
            'vat_amount' => (string) $orderTotals['vat_amount'],
            'carrier_cost' => (string) $orderTotals['carrier_cost'],
            'total_ttc' => (string) $orderTotals['total'],
        ];

        if ($orderReference) {
            $metadata['order_reference'] = $orderReference;
        }

        return (new StripeClient($this->stripeSecretKey))->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata,
        ]);
    }

    public function createSession(CartService $cartService, string $successUrl, string $cancelUrl): \Stripe\Checkout\Session
    {
        $lineItems = [];
        foreach ($cartService->getCart() as $item) {
            $priceTTC = $item['price_ttc'] * 100;
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $item['name'],
                    ],
                    'unit_amount' => $priceTTC,
                ],
                'quantity' => $item['quantity'],
            ];
        }

        return (new StripeClient($this->stripeSecretKey))->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);
    }

    public function handle(Request $request)
    {
        $signature = $request->headers->get('stripe-signature');
        $body = $request->getContent();
        $event = Webhook::constructEvent($body, $signature, $this->webhookSecretKey);

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                // Handle successful payment here
                break;
            case 'payment_intent.payment_failed':
                $paymentIntent = $event->data->object;
                // Handle failed payment here
                break;
            case 'checkout.session.completed':
                $session = $event->data->object;
                file_put_contents('stripe_webhook.log', 'Checkout session completed: '.json_encode($session, JSON_THROW_ON_ERROR)."\n", FILE_APPEND);
                // Handle completed checkout session here
                break;
        }
    }
}
