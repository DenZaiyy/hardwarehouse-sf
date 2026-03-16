<?php

namespace App\Service\Checkout;

use App\DTO\Checkout\CheckoutState;
use App\Repository\CarrierRepository;

final readonly class CheckoutDeliveryManager
{
    public function __construct(
        private CarrierRepository $carrierRepository,
    ) {
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    public function getCarriers(CheckoutState $state): array
    {
        $carriers = $this->carrierRepository->findAll();

        /** @var array<int, array{id: int, label: string}> */
        return array_values(array_filter(
            array_map(static fn ($carrier) => [
                'id' => $carrier->getId(),
                'label' => $carrier->getName().' - '.number_format((float) $carrier->getPrice(), 2, ',', ' ').' €',
            ], $carriers),
            static fn (array $carrier) => null !== $carrier['id'],
        ));
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
        if (!$state->carrierId) {
            return null;
        }

        $carrier = $this->carrierRepository->find($state->carrierId);

        if (!$carrier) {
            return null;
        }

        return $carrier->getName().' - '.number_format((float) $carrier->getPrice(), 2, ',', ' ').' €';
    }
}
