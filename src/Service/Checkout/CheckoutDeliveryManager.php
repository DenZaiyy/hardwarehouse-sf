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
     * Transporteurs proposés, du moins cher au plus cher, avec ce qu'affiche leur carte à l'étape de livraison. Le prix
     * est TTC, comme tous les montants de la boutique : le résumé de commande l'additionne tel quel.
     *
     * @return list<array{id: int, label: string, name: string, description: string|null, logo: string|null, price: float}>
     */
    public function getCarriers(CheckoutState $state): array
    {
        $carriers = [];
        foreach ($this->carrierRepository->findBy([], ['price' => 'ASC', 'id' => 'ASC']) as $carrier) {
            $id = $carrier->getId();
            if (null === $id) {
                continue;
            }

            $price = (float) $carrier->getPrice();
            $carriers[] = [
                'id' => $id,
                'label' => $carrier->getName().' - '.number_format($price, 2, ',', ' ').' €',
                'name' => (string) $carrier->getName(),
                'description' => $carrier->getDescription(),
                'logo' => $carrier->getLogo(),
                'price' => $price,
            ];
        }

        return $carriers;
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
