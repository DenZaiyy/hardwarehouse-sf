<?php

namespace App\Service\Checkout;

use App\DTO\Checkout\CheckoutState;

final class CheckoutFlowManager
{
    public function canAccessIdentityStep(CheckoutState $state): bool
    {
        return true;
    }

    public function canAccessAddressStep(CheckoutState $state): bool
    {
        return $state->identityCompleted;
    }

    public function canAccessDeliveryStep(CheckoutState $state): bool
    {
        return $state->identityCompleted && $state->addressCompleted;
    }

    public function canAccessPaymentStep(CheckoutState $state): bool
    {
        return $state->identityCompleted
            && $state->addressCompleted
            && $state->deliveryCompleted;
    }
}
