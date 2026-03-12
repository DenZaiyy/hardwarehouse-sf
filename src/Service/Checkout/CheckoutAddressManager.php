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
            'user_info' => $user,
            'type' => $type,
            'is_default' => true,
        ]);
    }

    public function findOwnedAddressById(User $user, int $addressId, AddressType $type): ?Address
    {
        return $this->addressRepository->findOneBy([
            'id' => $addressId,
            'user_info' => $user,
            'type' => $type,
        ]);
    }

    public function saveSelectedDeliveryAddress(CheckoutState $state, Address $address): CheckoutState
    {
        $state->deliveryAddressId = $address->getId();
        $state->billingAddressId = $address->getId();
        $state->deliveryAddress = [
            'label' => $address->getLabel(),
            'firstName' => $address->getFirstname(),
            'lastName' => $address->getLastname(),
            'address1' => $address->getAddress(),
            'postcode' => $address->getPostalCode(),
            'city' => $address->getCity(),
            'country' => $address->getCountry()?->value,
        ];
        $state->addressCompleted = true;
        $state->currentStep = 3;

        return $state;
    }

    public function createDeliveryAddressForUser(
        CheckoutState $state,
        User $user,
        AddressData $data,
        bool $setAsDefault = false,
    ): CheckoutState {
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
