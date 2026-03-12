<?php

namespace App\Service\Checkout;

use App\DTO\Checkout\CheckoutState;
use Symfony\Component\HttpFoundation\RequestStack;

final class CheckoutStateManager
{
    private const string SESSION_KEY = 'checkout_state';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getState(): CheckoutState
    {
        $data = $this->requestStack->getSession()->get(self::SESSION_KEY, []);

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
