<?php

namespace App\Service\Checkout;

use App\DTO\Checkout\CheckoutState;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class CheckoutStateManager
{
    private const string SESSION_KEY = 'checkout_state';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function getState(): CheckoutState
    {
        $data = $this->requestStack->getSession()->get(self::SESSION_KEY, []);

        if (!\is_array($data)) {
            return new CheckoutState();
        }

        /** @var array{currentStep?: int, identityMode?: string, identity?: array{title?: string|null, firstName?: string|null, lastName?: string|null, email?: string|null, username?: string|null}|null, deliveryAddress?: array{label?: string|null, firstName?: string|null, lastName?: string|null, address1?: string|null, postcode?: string|null, city?: string|null, country?: string|null}|null, deliveryAddressId?: int|null, billingAddressId?: int|null, carrierId?: int|null, identityCompleted?: bool, addressCompleted?: bool, deliveryCompleted?: bool, paymentCompleted?: bool} $data */
        return CheckoutState::fromArray($data);
    }

    public function saveState(CheckoutState $state): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $state->toArray());
    }

    public function reset(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }
}
