<?php

namespace App\Service\Checkout;

use App\DTO\Checkout\CheckoutState;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class CheckoutStateManager
{
    private const string SESSION_KEY = 'checkout_state';

    /** Référence de la dernière commande passée dans cette session (achat sans compte). */
    public const string ORDER_REFERENCE_KEY = 'checkout_order_reference';

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

    /**
     * Conservée à part de l'état du tunnel : reset() ne l'efface pas, si bien qu'un client sans
     * compte peut recharger sa page de confirmation pendant toute sa session.
     */
    public function rememberOrderReference(string $reference): void
    {
        $this->requestStack->getSession()->set(self::ORDER_REFERENCE_KEY, $reference);
    }

    public function remembersOrderReference(string $reference): bool
    {
        return $this->requestStack->getSession()->get(self::ORDER_REFERENCE_KEY) === $reference;
    }
}
