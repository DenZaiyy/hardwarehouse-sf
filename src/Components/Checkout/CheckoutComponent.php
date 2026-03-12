<?php

namespace App\Components\Checkout;

use App\DTO\Checkout\AddressData;
use App\DTO\Checkout\CheckoutState;
use App\DTO\Checkout\DeliveryChoiceData;
use App\DTO\Checkout\GuestIdentityData;
use App\Form\Checkout\CheckoutAddressType;
use App\Form\Checkout\DeliveryChoiceType;
use App\Form\Checkout\GuestIdentityType;
use App\Service\Checkout\CheckoutAddressManager;
use App\Service\Checkout\CheckoutDeliveryManager;
use App\Service\Checkout\CheckoutIdentityManager;
use App\Service\Checkout\CheckoutStateManager;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use App\Entity\User;
use App\Enum\AddressType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\LiveArg;

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
     * @return array<int, array<string, mixed>>
     */
    public function getSavedDeliveryAddresses(): array
    {
        $user = $this->getUser();

        if (!$user) {
            return [];
        }

        $addresses = $this->addressManager->getUserAddressesByType($user, AddressType::DELIVERY);

        return array_map(static function ($address): array {
            return [
                'id' => $address->getId(),
                'label' => $address->getLabel(),
                'firstName' => $address->getFirstName(),
                'lastName' => $address->getLastName(),
                'address1' => $address->getAddress(),
                'postcode' => $address->getPostalCode(),
                'city' => $address->getCity(),
                'country' => $address->getCountry()?->value,
                'isDefault' => $address->isDefault(),
            ];
        }, $addresses);
    }

    public function shouldShowAddressSelection(): bool
    {
        return $this->getState()->currentStep === 2
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

        if ($state->currentStep === 1 && $state->identityMode === 'guest') {
            $data = new GuestIdentityData();
            $data->title = $state->identity['title'] ?? null;
            $data->firstName = $state->identity['firstName'] ?? null;
            $data->lastName = $state->identity['lastName'] ?? null;
            $data->email = $state->identity['email'] ?? null;

            return $this->formFactory->create(GuestIdentityType::class, $data);
        }

        if ($state->currentStep === 2) {
            $data = new AddressData();
            $data->label = $state->deliveryAddress['label'] ?? 'Domicile';
            $data->firstName = $state->deliveryAddress['firstName'] ?? ($state->identity['firstName'] ?? null);
            $data->lastName = $state->deliveryAddress['lastName'] ?? ($state->identity['lastName'] ?? null);
            $data->address1 = $state->deliveryAddress['address1'] ?? null;
            $data->postcode = $state->deliveryAddress['postcode'] ?? null;
            $data->city = $state->deliveryAddress['city'] ?? null;
            $data->country = $state->deliveryAddress['country'] ?? 'FR';

            return $this->formFactory->create(CheckoutAddressType::class, $data);
        }

        if ($state->currentStep === 3) {
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

        if ($state->identityMode === 'authenticated') {
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
        return $this->getState()->identityMode === 'authenticated';
    }
}
