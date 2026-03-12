<?php

namespace App\Service\Checkout;

use App\DTO\Checkout\CheckoutState;
use App\Repository\CarrierRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CheckoutDeliveryManager
{
    public function __construct(
        private readonly CarrierRepository $carrierRepository,
    )
    {
    }

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
