<?php

namespace App\Service\Checkout;

use App\DTO\Checkout\CheckoutState;

final readonly class CheckoutDeliveryManager
{
    /**
     * @return array<int, array{id: int, label: string}>
     */
    public function getCarriers(CheckoutState $state): array
    {
        return [
            ['id' => 1, 'label' => 'Livraison standard - 4,90 €'],
            ['id' => 2, 'label' => 'Livraison express - 9,90 €'],
        ];
    }

    public function saveCarrier(CheckoutState $state, int $carrierId): CheckoutState
    {
        $state->carrierId = $carrierId;
        $state->deliveryCompleted = true;
        $state->currentStep = 4;

        return $state;
    }

    public function getCarrierLabel(CheckoutState $state): ?string
    {
        foreach ($this->getCarriers($state) as $carrier) {
            if ($carrier['id'] === $state->carrierId) {
                return $carrier['label'];
            }
        }

        return null;
    }
}
