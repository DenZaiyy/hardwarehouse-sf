<?php

namespace App\Service;

use App\Entity\Order;
use App\Service\Pricing\PriceCalculator;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class StripeService
{
    public function __construct(
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private readonly string $stripeSecretKey,
        private readonly PriceCalculator $priceCalculator,
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
        Stripe::setApiVersion('');
    }

    /**
     * @param string $paymentMethodType Stripe payment method type chosen in the shop: card or paypal
     */
    public function createCheckoutSession(Order $order, ?string $carrierLabel, string $paymentMethodType, string $successUrl, string $cancelUrl): Session
    {
        return (new StripeClient($this->stripeSecretKey))->checkout->sessions->create(
            $this->checkoutSessionParameters($order, $carrierLabel, $paymentMethodType, $successUrl, $cancelUrl),
        );
    }

    /**
     * Built from the order, not from the cart: Stripe bills the discounted unit prices recorded on the
     * order lines, rounded like the order totals, so the amount charged is the order total to the cent.
     *
     * The customer picks card or PayPal in the shop, so the Stripe page only offers that method. A
     * method must be enabled in the Stripe Dashboard (Settings > Payment methods) to be accepted.
     *
     * @param string $paymentMethodType Stripe payment method type chosen in the shop: card or paypal
     *
     * @return array{
     *     payment_method_types: list<string>,
     *     line_items: list<array{price_data: array{currency: string, product_data: array{name: string, description?: string}, unit_amount: int}, quantity: int}>,
     *     mode: string,
     *     success_url: string,
     *     cancel_url: string,
     *     metadata: array<string, string>,
     *     payment_intent_data: array{metadata: array<string, string>}
     * }
     */
    public function checkoutSessionParameters(Order $order, ?string $carrierLabel, string $paymentMethodType, string $successUrl, string $cancelUrl): array
    {
        $lineItems = [];

        foreach ($order->getOrderLines() as $line) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => ['name' => (string) $line->getProductName()],
                    'unit_amount' => $this->priceCalculator->unitPriceIncludingTax((float) $line->getUnitPrice()),
                ],
                'quantity' => (int) $line->getQuantity(),
            ];
        }

        $shipping = (int) round((float) $order->getShippingAmount() * 100);
        if ($shipping > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => ['name' => 'Frais de livraison', 'description' => (string) $carrierLabel],
                    'unit_amount' => $shipping,
                ],
                'quantity' => 1,
            ];
        }

        $metadata = [
            'order_reference' => (string) $order->getReference(),
            'subtotal_ht' => (string) $order->getSubtotal(),
            'vat_amount' => (string) $order->getTaxAmount(),
            'carrier_cost' => (string) $order->getShippingAmount(),
            'total_ttc' => (string) $order->getTotalAmount(),
        ];

        return [
            'payment_method_types' => [$paymentMethodType],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata,
            // Checkout Session metadata is NOT copied to the underlying PaymentIntent by
            // Stripe. Without this, payment_intent.* webhook events (succeeded, failed,
            // canceled...) have no order_reference to resolve the order from.
            'payment_intent_data' => [
                'metadata' => $metadata,
            ],
        ];
    }
}
