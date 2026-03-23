<?php

namespace App\DTO\Checkout;

/**
 * @phpstan-type IdentityMode 'choice'|'guest'|'login'|'authenticated'
 * @phpstan-type CheckoutIdentity array{
 *     title?: string|null,
 *     firstName?: string|null,
 *     lastName?: string|null,
 *     email?: string|null,
 *     username?: string|null
 * }
 * @phpstan-type DeliveryAddress array{
 *     label?: string|null,
 *     firstName?: string|null,
 *     lastName?: string|null,
 *     address1?: string|null,
 *     postcode?: string|null,
 *     city?: string|null,
 *     country?: string|null
 * }
 * @phpstan-type CheckoutStateArray array{
 *     currentStep?: int,
 *     identityMode?: string,
 *     identity?: CheckoutIdentity|null,
 *     deliveryAddress?: DeliveryAddress|null,
 *     deliveryAddressId?: int|null,
 *     billingAddressId?: int|null,
 *     carrierId?: int|null,
 *     showAddressForm?: bool,
 *     paymentMethod?: string|null,
 *     identityCompleted?: bool,
 *     addressCompleted?: bool,
 *     deliveryCompleted?: bool,
 *     paymentCompleted?: bool
 * }
 */
final class CheckoutState
{
    /**
     * @param IdentityMode          $identityMode
     * @param CheckoutIdentity|null $identity
     * @param DeliveryAddress|null  $deliveryAddress
     */
    public function __construct(
        public int $currentStep = 1,
        public string $identityMode = 'choice',
        public ?array $identity = null,
        public ?array $deliveryAddress = null,
        public ?int $deliveryAddressId = null,
        public ?int $billingAddressId = null,
        public ?int $carrierId = null,
        public bool $showAddressForm = false,
        public ?string $paymentMethod = null,
        public bool $identityCompleted = false,
        public bool $addressCompleted = false,
        public bool $deliveryCompleted = false,
        public bool $paymentCompleted = false,
    ) {
    }

    /**
     * @return CheckoutStateArray
     */
    public function toArray(): array
    {
        return [
            'currentStep' => $this->currentStep,
            'identityMode' => $this->identityMode,
            'identity' => $this->identity,
            'deliveryAddress' => $this->deliveryAddress,
            'deliveryAddressId' => $this->deliveryAddressId,
            'billingAddressId' => $this->billingAddressId,
            'carrierId' => $this->carrierId,
            'showAddressForm' => $this->showAddressForm,
            'paymentMethod' => $this->paymentMethod,
            'identityCompleted' => $this->identityCompleted,
            'addressCompleted' => $this->addressCompleted,
            'deliveryCompleted' => $this->deliveryCompleted,
            'paymentCompleted' => $this->paymentCompleted,
        ];
    }

    /**
     * @param CheckoutStateArray $data
     */
    public static function fromArray(array $data): self
    {
        $rawMode = $data['identityMode'] ?? 'choice';
        /** @var IdentityMode $identityMode */
        $identityMode = \in_array($rawMode, ['choice', 'guest', 'login', 'authenticated'], true)
            ? $rawMode
            : 'choice';

        return new self(
            currentStep: $data['currentStep'] ?? 1,
            identityMode: $identityMode,
            identity: $data['identity'] ?? null,
            deliveryAddress: $data['deliveryAddress'] ?? null,
            deliveryAddressId: $data['deliveryAddressId'] ?? null,
            billingAddressId: $data['billingAddressId'] ?? null,
            carrierId: $data['carrierId'] ?? null,
            showAddressForm: $data['showAddressForm'] ?? false,
            paymentMethod: $data['paymentMethod'] ?? null,
            identityCompleted: $data['identityCompleted'] ?? false,
            addressCompleted: $data['addressCompleted'] ?? false,
            deliveryCompleted: $data['deliveryCompleted'] ?? false,
            paymentCompleted: $data['paymentCompleted'] ?? false,
        );
    }
}
