<?php

namespace App\Service;

use App\DTO\Checkout\CheckoutState;
use App\Entity\Order;
use App\Entity\OrderAddress;
use App\Entity\OrderLine;
use App\Entity\User;
use App\Enum\AddressType;
use App\Enum\OrderStatus;
use App\Repository\CarrierRepository;
use App\Service\Pricing\PriceCalculator;
use Doctrine\ORM\EntityManagerInterface;

class OrderService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CartService $cartService,
        private readonly CarrierRepository $carrierRepository,
        private readonly PriceCalculator $priceCalculator,
    ) {
    }

    /**
     * Create an order from current cart and checkout state.
     */
    public function createOrderFromCartAndCheckout(CheckoutState $checkoutState, ?User $user = null): Order
    {
        // Verify checkout is complete
        if (!$checkoutState->identityCompleted || !$checkoutState->addressCompleted || !$checkoutState->deliveryCompleted) {
            throw new \LogicException('Checkout is not complete');
        }

        // Get cart data
        $cartItems = $this->cartService->getCart();
        if (empty($cartItems)) {
            throw new \LogicException('Cart is empty');
        }

        // Totaux en centimes, calculés comme ceux du panier et de la session Stripe
        $totals = $this->priceCalculator->totals(array_map(
            static fn (array $item): array => ['unit_price' => $item['effective_ht'], 'quantity' => $item['quantity']],
            array_values($cartItems),
        ));
        $shipping = (int) round($this->getCarrierCost($checkoutState->carrierId) * 100);

        // Create order
        $order = new Order();
        $order->setReference($this->generateOrderReference());
        $order->setUser($user);

        // Set username snapshot
        if ($user && $user->getUsername()) {
            $order->setUserFullNameSnapshot($user->getUsername());
        } else {
            $identity = $checkoutState->identity;
            $fullName = trim(($identity['firstName'] ?? '').' '.($identity['lastName'] ?? ''));
            $order->setUserFullNameSnapshot($fullName ?: 'Guest');
        }

        // Set financial data
        $order->setSubtotal(PriceCalculator::toDecimal($totals['subtotal']));
        $order->setTaxAmount(PriceCalculator::toDecimal($totals['vat']));
        $order->setShippingAmount(PriceCalculator::toDecimal($shipping));
        $order->setDiscountAmount('0.00');
        $order->setTotalAmount(PriceCalculator::toDecimal($totals['total'] + $shipping));
        $order->setCurrency('EUR');
        $order->setStatus(OrderStatus::PENDING);

        $this->entityManager->persist($order);

        // Create order lines from cart items
        foreach ($cartItems as $cartItem) {
            $orderLine = new OrderLine();
            $orderLine->setOrder($order);
            $orderLine->setProductId($cartItem['productId']);
            $orderLine->setProductName($cartItem['name']);
            $orderLine->setProductSlug($cartItem['slug']);
            $orderLine->setQuantity($cartItem['quantity']);

            // Prix unitaire remisé s'il y a lieu ; le total TTC de la ligne est celui que Stripe facture
            $unitPrice = $cartItem['effective_ht'];
            $orderLine->setUnitPrice(PriceCalculator::toDecimal((int) round($unitPrice * 100)));
            $orderLine->setTaxRate((string) PriceCalculator::VAT_RATE);
            $orderLine->setLineTotal(PriceCalculator::toDecimal($this->priceCalculator->unitPriceIncludingTax($unitPrice) * $cartItem['quantity']));

            $order->addOrderLine($orderLine);
            $this->entityManager->persist($orderLine);
        }

        // Create delivery address
        $deliveryAddress = $this->createOrderAddress($order, $checkoutState, AddressType::DELIVERY);
        $this->entityManager->persist($deliveryAddress);

        // Create billing address (same as delivery for now)
        $billingAddress = $this->createOrderAddress($order, $checkoutState, AddressType::BILLING);
        $this->entityManager->persist($billingAddress);

        $this->entityManager->flush();

        return $order;
    }

    /**
     * Generate a unique order reference.
     */
    private function generateOrderReference(): string
    {
        $prefix = 'ORD';
        $timestamp = date('Ymd');
        $random = strtoupper(bin2hex(random_bytes(4)));

        return $prefix.$timestamp.$random;
    }

    /**
     * Create an order address from checkout state.
     */
    private function createOrderAddress(Order $order, CheckoutState $checkoutState, AddressType $type): OrderAddress
    {
        $orderAddress = new OrderAddress();
        $orderAddress->setOrder($order);
        $orderAddress->setType($type);

        // Billing falls back to the delivery address whenever the customer didn't pick a
        // distinct one (billingSameAsDelivery, or a guest who never filled the billing form).
        $useDistinctBillingAddress = AddressType::BILLING === $type
            && !$checkoutState->billingSameAsDelivery
            && null !== $checkoutState->billingAddress;

        $addressData = $useDistinctBillingAddress ? $checkoutState->billingAddress : $checkoutState->deliveryAddress;
        $identity = $checkoutState->identity;

        $orderAddress->setFirstName($addressData['firstName'] ?? ($identity['firstName'] ?? ''));
        $orderAddress->setLastName($addressData['lastName'] ?? ($identity['lastName'] ?? ''));
        $orderAddress->setAddress($addressData['address1'] ?? '');
        $orderAddress->setPostalCode($addressData['postcode'] ?? '');
        $orderAddress->setCity($addressData['city'] ?? '');
        $orderAddress->setCountry($addressData['country'] ?? 'FR');

        $order->addOrderAddress($orderAddress);

        return $orderAddress;
    }

    /**
     * Get carrier cost by carrier ID.
     */
    private function getCarrierCost(?int $carrierId): float
    {
        if (!$carrierId) {
            return 0.0;
        }

        $carrier = $this->carrierRepository->find($carrierId);

        if (!$carrier) {
            return 0.0;
        }

        return (float) $carrier->getPrice();
    }

    /**
     * Update order status.
     */
    public function updateOrderStatus(Order $order, OrderStatus $status): void
    {
        $order->setStatus($status);
        $this->entityManager->flush();
    }
}
