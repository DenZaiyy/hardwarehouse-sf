<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Order;
use App\Entity\OrderLine;
use App\Service\Pricing\PriceCalculator;
use App\Service\StripeService;
use PHPUnit\Framework\TestCase;

final class StripeServiceTest extends TestCase
{
    public function testCheckoutBillsTheDiscountedPricesOfTheOrder(): void
    {
        $order = (new Order())->setReference('ORDTEST')->setShippingAmount('4.90')->setTotalAmount('664.90');
        // Prix remisé : 183,33 € HT au lieu de 219,99 €
        $order->addOrderLine((new OrderLine())->setProductName('Ryzen 7 7800X3D')->setUnitPrice('183.33')->setQuantity(3)->setTaxRate('0.2')->setLineTotal('660.00'));

        $parameters = (new StripeService('sk_test_unused', new PriceCalculator()))
            ->checkoutSessionParameters($order, 'Colissimo', 'card', 'https://example.com/ok', 'https://example.com/annule');

        $lines = $parameters['line_items'];
        self::assertSame(22000, $lines[0]['price_data']['unit_amount']);
        self::assertSame(3, $lines[0]['quantity']);
        self::assertSame(490, $lines[1]['price_data']['unit_amount']);
        self::assertSame(66490, array_sum(array_map(static fn (array $line): int => $line['price_data']['unit_amount'] * $line['quantity'], $lines)));
        self::assertSame('ORDTEST', $parameters['metadata']['order_reference']);
    }

    public function testCheckoutOffersOnlyThePaymentMethodChosenInTheShop(): void
    {
        $order = (new Order())->setReference('ORDTEST')->setShippingAmount('4.90')->setTotalAmount('544.78');
        $order->addOrderLine((new OrderLine())->setProductName('Ryzen 7 7800X3D')->setUnitPrice('449.90')->setQuantity(1)->setTaxRate('0.2')->setLineTotal('539.88'));

        $parameters = (new StripeService('sk_test_unused', new PriceCalculator()))
            ->checkoutSessionParameters($order, 'Colissimo', 'paypal', 'https://example.com/ok', 'https://example.com/annule');

        self::assertSame(['paypal'], $parameters['payment_method_types']);
    }
}
