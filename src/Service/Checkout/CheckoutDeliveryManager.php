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
        // L'identifiant vient du navigateur : sans transporteur réel, OrderService compterait 0 € de port
        if (null === $this->carrierRepository->find($carrierId)) {
            return $state;
        }

        $state->carrierId = $carrierId;
        $state->deliveryCompleted = true;
        $state->currentStep = 4;

        return $state;
    }

    /**
     * Le transporteur choisi a pu être supprimé depuis : plutôt que de compter 0 € de port, l'étape
     * de livraison est rouverte.
     *
     * @return bool false si le client doit choisir un autre transporteur
     */
    public function ensureCarrierStillAvailable(CheckoutState $state): bool
    {
        if (null !== $state->carrierId && null !== $this->carrierRepository->find($state->carrierId)) {
            return true;
        }

        $state->carrierId = null;
        $state->deliveryCompleted = false;
        $state->currentStep = 3;

        return false;
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
