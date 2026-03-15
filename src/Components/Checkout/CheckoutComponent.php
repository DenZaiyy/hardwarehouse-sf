<?php

namespace App\Components\Checkout;

use App\DTO\Checkout\AddressData;
use App\DTO\Checkout\CheckoutState;
use App\DTO\Checkout\DeliveryChoiceData;
use App\DTO\Checkout\GuestIdentityData;
use App\Entity\User;
use App\Enum\AddressType;
use App\Form\Checkout\CheckoutAddressType;
use App\Form\Checkout\DeliveryChoiceType;
use App\Form\Checkout\GuestIdentityType;
use App\Service\CartService;
use App\Service\Checkout\CheckoutAddressManager;
use App\Service\Checkout\CheckoutDeliveryManager;
use App\Service\Checkout\CheckoutIdentityManager;
use App\Service\Checkout\CheckoutStateManager;
use App\Service\OrderService;
use App\Service\StripeService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('Checkout:CheckoutComponent')]
final class CheckoutComponent
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;

    public function __construct(
        private readonly CheckoutStateManager $stateManager,
        private readonly CheckoutIdentityManager $identityManager,
        private readonly CheckoutAddressManager $addressManager,
        private readonly CheckoutDeliveryManager $deliveryManager,
        private readonly CartService $cartService,
        private readonly OrderService $orderService,
        private readonly StripeService $stripeService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly FormFactoryInterface $formFactory,
        private readonly AuthenticationUtils $authenticationUtils,
        private readonly Security $security,
    ) {
    }

    public function getUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    public function hasSavedDeliveryAddresses(): bool
    {
        $user = $this->getUser();

        if (!$user) {
            return false;
        }

        return \count($this->addressManager->getUserAddressesByType($user, AddressType::DELIVERY)) > 0;
    }

    /**
     * @return list<array{id: int|null, label: string|null, firstName: string|null, lastName: string|null, address1: string|null, postcode: string|null, city: string|null, country: string|null, isDefault: bool|null}>
     */
    public function getSavedDeliveryAddresses(): array
    {
        $user = $this->getUser();

        if (!$user) {
            return [];
        }

        $addresses = $this->addressManager->getUserAddressesByType($user, AddressType::DELIVERY);

        return array_values(array_map(static fn ($address): array => [
            'id' => $address->getId(),
            'label' => $address->getLabel(),
            'firstName' => $address->getFirstName(),
            'lastName' => $address->getLastName(),
            'address1' => $address->getAddress(),
            'postcode' => $address->getPostalCode(),
            'city' => $address->getCity(),
            'country' => $address->getCountry()?->value,
            'isDefault' => $address->isDefault(),
        ], $addresses));
    }

    public function shouldShowAddressSelection(): bool
    {
        return 2 === $this->getState()->currentStep
            && $this->getUser() instanceof User
            && $this->hasSavedDeliveryAddresses();
    }

    public function mount(): void
    {
        $state = $this->identityManager->syncAuthenticatedUser($this->stateManager->getState());

        if ($this->authenticationUtils->getLastAuthenticationError()) {
            $state->identityMode = 'login';
            $state->currentStep = 1;
        }

        $this->stateManager->saveState($state);
    }

    public function getState(): CheckoutState
    {
        return $this->stateManager->getState();
    }

    protected function instantiateForm(): FormInterface
    {
        $state = $this->getState();

        if (1 === $state->currentStep && 'guest' === $state->identityMode) {
            $identity = $state->identity;
            $data = new GuestIdentityData();
            $data->title = $identity['title'] ?? null;
            $data->firstName = $identity['firstName'] ?? null;
            $data->lastName = $identity['lastName'] ?? null;
            $data->email = $identity['email'] ?? null;

            return $this->formFactory->create(GuestIdentityType::class, $data);
        }

        if (2 === $state->currentStep) {
            $identity = $state->identity;
            $deliveryAddress = $state->deliveryAddress;

            $data = new AddressData();
            $data->label = $deliveryAddress['label'] ?? 'Domicile';
            $data->firstName = $deliveryAddress['firstName'] ?? ($identity['firstName'] ?? null);
            $data->lastName = $deliveryAddress['lastName'] ?? ($identity['lastName'] ?? null);
            $data->address1 = $deliveryAddress['address1'] ?? null;
            $data->postcode = $deliveryAddress['postcode'] ?? null;
            $data->city = $deliveryAddress['city'] ?? null;
            $data->country = $deliveryAddress['country'] ?? 'FR';

            return $this->formFactory->create(CheckoutAddressType::class, $data);
        }

        if (3 === $state->currentStep) {
            $data = new DeliveryChoiceData();
            $data->carrierId = $state->carrierId;

            return $this->formFactory->create(DeliveryChoiceType::class, $data, [
                'carriers' => $this->deliveryManager->getCarriers($state),
            ]);
        }

        return $this->formFactory->create(GuestIdentityType::class, new GuestIdentityData());
    }

    public function isStepCompleted(int $step): bool
    {
        $state = $this->getState();

        return match ($step) {
            1 => $state->identityCompleted,
            2 => $state->addressCompleted,
            3 => $state->deliveryCompleted,
            4 => $state->paymentCompleted,
            default => false,
        };
    }

    #[LiveAction]
    public function chooseGuest(): void
    {
        $state = $this->getState();
        $state->identityMode = 'guest';
        $state->currentStep = 1;

        $this->stateManager->saveState($state);
    }

    #[LiveAction]
    public function chooseLogin(): void
    {
        $state = $this->getState();
        $state->identityMode = 'login';
        $state->currentStep = 1;

        $this->stateManager->saveState($state);
    }

    #[LiveAction]
    public function saveGuest(): void
    {
        $this->submitForm();

        /** @var GuestIdentityData $data */
        $data = $this->getForm()->getData();
        $state = $this->identityManager->saveGuestIdentity($this->getState(), $data);

        $this->stateManager->saveState($state);
    }

    #[LiveAction]
    public function saveAddress(): void
    {
        $this->submitForm();

        /** @var AddressData $data */
        $data = $this->getForm()->getData();
        $state = $this->getState();
        $user = $this->getUser();

        if ($user instanceof User) {
            $hasExisting = \count($this->addressManager->getUserAddressesByType($user, AddressType::DELIVERY)) > 0;

            $state = $this->addressManager->createDeliveryAddressForUser(
                $state,
                $user,
                $data,
                !$hasExisting
            );
        } else {
            $state = $this->addressManager->saveGuestAddress($state, $data);
        }

        $this->stateManager->saveState($state);
    }

    #[LiveAction]
    public function useNewAddressForm(): void
    {
        $state = $this->getState();
        $state->deliveryAddressId = null;

        $this->stateManager->saveState($state);
    }

    #[LiveAction]
    public function saveDeliveryChoice(): void
    {
        $this->submitForm();

        /** @var DeliveryChoiceData $data */
        $data = $this->getForm()->getData();
        $state = $this->deliveryManager->saveCarrier($this->getState(), (int) $data->carrierId);

        $this->stateManager->saveState($state);
    }

    #[LiveAction]
    public function selectDeliveryAddress(#[LiveArg] int $addressId): void
    {
        $user = $this->getUser();

        if (!$user) {
            return;
        }

        $address = $this->addressManager->findOwnedAddressById($user, $addressId, AddressType::DELIVERY);

        if (!$address) {
            return;
        }

        $state = $this->addressManager->saveSelectedDeliveryAddress($this->getState(), $address);
        $this->stateManager->saveState($state);
    }

    #[LiveAction]
    public function editIdentity(): void
    {
        $state = $this->getState();
        $state->currentStep = 1;

        if ('authenticated' === $state->identityMode) {
            return;
        }

        $this->stateManager->saveState($state);
    }

    #[LiveAction]
    public function editAddress(): void
    {
        $state = $this->getState();

        if ($state->identityCompleted) {
            $state->currentStep = 2;
            $this->stateManager->saveState($state);
        }
    }

    #[LiveAction]
    public function editDelivery(): void
    {
        $state = $this->getState();

        if ($state->identityCompleted && $state->addressCompleted) {
            $state->currentStep = 3;
            $this->stateManager->saveState($state);
        }
    }

    #[LiveAction]
    public function finalizePayment(): void
    {
        $state = $this->getState();

        if ($state->identityCompleted && $state->addressCompleted && $state->deliveryCompleted) {
            $state->paymentCompleted = true;
            $state->currentStep = 4;

            $this->stateManager->saveState($state);
        }
    }

    public function getSelectedCarrierLabel(): ?string
    {
        return $this->deliveryManager->getCarrierLabel($this->getState());
    }

    public function getLoginError(): ?string
    {
        return $this->authenticationUtils->getLastAuthenticationError()?->getMessageKey();
    }

    public function getLastUsername(): string
    {
        return $this->authenticationUtils->getLastUsername();
    }

    public function isAuthenticatedStepSkipped(): bool
    {
        return 'authenticated' === $this->getState()->identityMode;
    }

    /**
     * @return array<string, array{productId: string, quantity: int, remaining_stock: int, category: string, name: string, price_ht: float, price_ttc: float, imageUrl: string, slug: string}>
     */
    public function getCartItems(): array
    {
        return $this->cartService->getCart();
    }

    public function getCartCount(): int
    {
        return $this->cartService->getCount();
    }

    /**
     * Get cart totals including carrier costs
     * @return array{subtotal: float, vat_rate: float, vat_amount: float, carrier_cost: float, total: float}
     */
    public function getOrderTotals(): array
    {
        $cartTotals = $this->cartService->computeTotals();
        $carrierCost = $this->getCarrierCost();

        $totalWithCarrier = $cartTotals['total'] + $carrierCost;

        return [
            'subtotal' => $cartTotals['subtotal'],
            'vat_rate' => $cartTotals['vat_rate'],
            'vat_amount' => $cartTotals['vat_amount'],
            'carrier_cost' => $carrierCost,
            'total' => $totalWithCarrier,
        ];
    }

    private function getCarrierCost(): float
    {
        $state = $this->getState();

        if (!$state->carrierId) {
            return 0.0;
        }

        // Find selected carrier cost
        $carriers = $this->deliveryManager->getCarriers($state);

        foreach ($carriers as $carrier) {
            if ($carrier['id'] === $state->carrierId) {
                // Extract price from label (format: "Name - X,XX €")
                if (preg_match('/(\d+,\d+)\s*€/', $carrier['label'], $matches)) {
                    return (float) str_replace(',', '.', $matches[1]);
                }
            }
        }

        return 0.0;
    }

    #[LiveAction]
    public function processPayment(): RedirectResponse
    {
        $state = $this->getState();

        // Verify checkout is complete
        if (!$state->identityCompleted || !$state->addressCompleted || !$state->deliveryCompleted) {
            throw new \LogicException('Checkout is not complete');
        }

        // Verify cart is not empty
        $cartItems = $this->getCartItems();
        if (empty($cartItems)) {
            throw new \LogicException('Cart is empty');
        }

        // Create order with PENDING status before payment
        $user = $this->getUser();
        $order = $this->orderService->createOrderFromCartAndCheckout(
            $state,
            $user instanceof User ? $user : null
        );

        // Create Stripe checkout session with order reference
        $orderTotals = $this->getOrderTotals();
        $carrierLabel = $this->getSelectedCarrierLabel();

        $successUrl = $this->urlGenerator->generate('payment.success', ['reference' => $order->getReference()], UrlGeneratorInterface::ABSOLUTE_URL);
        $cancelUrl = $this->urlGenerator->generate('checkout.index', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $session = $this->stripeService->createCheckoutSession(
            $orderTotals,
            $cartItems,
            $carrierLabel,
            $successUrl,
            $cancelUrl,
            $order->getReference()
        );

        return new RedirectResponse($session->url);
    }
}
