<?php

namespace App\Service\Checkout;

use App\DTO\Checkout\AddressData;
use App\DTO\Checkout\CheckoutState;
use App\Entity\Address;
use App\Entity\User;
use App\Enum\AddressType;
use App\Enum\CountryList;
use App\Repository\AddressRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CheckoutAddressManager
{
    public function __construct(
        private AddressRepository $addressRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return Address[]
     */
    public function getUserAddressesByType(User $user, AddressType $type): array
    {
        return $this->addressRepository->findBy(
            [
                'user' => $user,
                'type' => $type,
            ],
            [
                'is_default' => 'DESC',
                'id' => 'DESC',
            ]
        );
    }

    public function getDefaultUserAddressByType(User $user, AddressType $type): ?Address
    {
        return $this->addressRepository->findOneBy([
            'user' => $user,
            'type' => $type,
            'is_default' => true,
        ]);
    }

    public function findOwnedAddressById(User $user, int $addressId, AddressType $type): ?Address
    {
        return $this->addressRepository->findOneBy([
            'id' => $addressId,
            'user' => $user,
            'type' => $type,
        ]);
    }

    public function saveSelectedDeliveryAddress(CheckoutState $state, Address $address): CheckoutState
    {
        $state->deliveryAddressId = $address->getId();
        $state->deliveryAddress = [
            'label' => $address->getLabel(),
            'firstName' => $address->getFirstname(),
            'lastName' => $address->getLastname(),
            'address1' => $address->getAddress(),
            'postcode' => $address->getPostalCode(),
            'city' => $address->getCity(),
            'country' => $address->getCountry()?->value,
        ];

        if ($state->billingSameAsDelivery) {
            $state->billingAddressId = $address->getId();
        }

        $state->addressCompleted = true;
        $state->currentStep = 3;

        return $state;
    }

    public function saveBillingSameAsDelivery(CheckoutState $state, bool $sameAsDelivery): CheckoutState
    {
        $state->billingSameAsDelivery = $sameAsDelivery;

        if ($sameAsDelivery) {
            $state->billingAddressId = $state->deliveryAddressId;
            $state->billingAddress = null;
            $state->showBillingAddressForm = false;
            $state->billingCompleted = true;
        } else {
            $state->billingCompleted = false;
        }

        return $state;
    }

    /**
     * @return Address[]
     */
    public function getUserBillingAddresses(User $user): array
    {
        return $this->getUserAddressesByType($user, AddressType::BILLING);
    }

    public function findOwnedBillingAddressById(User $user, int $addressId): ?Address
    {
        return $this->findOwnedAddressById($user, $addressId, AddressType::BILLING);
    }

    public function saveSelectedBillingAddress(CheckoutState $state, Address $address): CheckoutState
    {
        $state->billingAddressId = $address->getId();
        $state->billingAddress = [
            'label' => $address->getLabel(),
            'firstName' => $address->getFirstname(),
            'lastName' => $address->getLastname(),
            'address1' => $address->getAddress(),
            'postcode' => $address->getPostalCode(),
            'city' => $address->getCity(),
            'country' => $address->getCountry()?->value,
        ];
        $state->showBillingAddressForm = false;
        $state->billingCompleted = true;

        return $state;
    }

    public function createBillingAddressForUser(
        CheckoutState $state,
        User $user,
        AddressData $data,
        bool $setAsDefault = false,
    ): CheckoutState {
        if (
            null === $data->label
            || null === $data->firstName
            || null === $data->lastName
            || null === $data->address1
            || null === $data->postcode
            || null === $data->city
            || null === $data->country
        ) {
            throw new \InvalidArgumentException('All address fields are required to create a billing address.');
        }

        $address = new Address();
        $address
            ->setLabel($data->label)
            ->setFirstname($data->firstName)
            ->setLastname($data->lastName)
            ->setAddress($data->address1)
            ->setPostalCode($data->postcode)
            ->setCity($data->city)
            ->setCountry(CountryList::from($data->country))
            ->setType(AddressType::BILLING)
            ->setIsDefault($setAsDefault)
            ->setUser($user)
            ->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))
        ;

        $this->entityManager->persist($address);
        $this->entityManager->flush();

        return $this->saveSelectedBillingAddress($state, $address);
    }

    public function saveGuestBillingAddress(CheckoutState $state, AddressData $data): CheckoutState
    {
        $state->billingAddress = [
            'label' => $data->label,
            'firstName' => $data->firstName,
            'lastName' => $data->lastName,
            'address1' => $data->address1,
            'postcode' => $data->postcode,
            'city' => $data->city,
            'country' => $data->country,
        ];
        $state->billingAddressId = null;
        $state->showBillingAddressForm = false;
        $state->billingCompleted = true;

        return $state;
    }

    public function createDeliveryAddressForUser(
        CheckoutState $state,
        User $user,
        AddressData $data,
        bool $setAsDefault = false,
    ): CheckoutState {
        if (
            null === $data->label
            || null === $data->firstName
            || null === $data->lastName
            || null === $data->address1
            || null === $data->postcode
            || null === $data->city
            || null === $data->country
        ) {
            throw new \InvalidArgumentException('All address fields are required to create a delivery address.');
        }

        $address = new Address();
        $address
            ->setLabel($data->label)
            ->setFirstname($data->firstName)
            ->setLastname($data->lastName)
            ->setAddress($data->address1)
            ->setPostalCode($data->postcode)
            ->setCity($data->city)
            ->setCountry(CountryList::from($data->country))
            ->setType(AddressType::DELIVERY)
            ->setIsDefault($setAsDefault)
            ->setUser($user)
            ->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))
        ;

        $this->entityManager->persist($address);
        $this->entityManager->flush();

        return $this->saveSelectedDeliveryAddress($state, $address);
    }

    public function saveGuestAddress(CheckoutState $state, AddressData $data): CheckoutState
    {
        $state->deliveryAddress = [
            'label' => $data->label,
            'firstName' => $data->firstName,
            'lastName' => $data->lastName,
            'address1' => $data->address1,
            'postcode' => $data->postcode,
            'city' => $data->city,
            'country' => $data->country,
        ];

        $state->deliveryAddressId = null;
        $state->billingAddressId = null;
        $state->addressCompleted = true;
        $state->currentStep = 3;

        return $state;
    }
}
