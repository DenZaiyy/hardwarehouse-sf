<?php

namespace App\DTO\Checkout;

final class CheckoutState
{
    public function __construct(
        public int $currentStep = 1,
        public string $identityMode = 'choice', // choice|guest|login|authenticated
        public ?array $identity = null,
        public ?array $deliveryAddress = null,
        public ?int $deliveryAddressId = null,
        public ?int $billingAddressId = null,
        public ?int $carrierId = null,
        public bool $identityCompleted = false,
        public bool $addressCompleted = false,
        public bool $deliveryCompleted = false,
        public bool $paymentCompleted = false,
    ) {
    }

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
            'identityCompleted' => $this->identityCompleted,
            'addressCompleted' => $this->addressCompleted,
            'deliveryCompleted' => $this->deliveryCompleted,
            'paymentCompleted' => $this->paymentCompleted,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            currentStep: $data['currentStep'] ?? 1,
            identityMode: $data['identityMode'] ?? 'choice',
            identity: $data['identity'] ?? null,
            deliveryAddress: $data['deliveryAddress'] ?? null,
            deliveryAddressId: $data['deliveryAddressId'] ?? null,
            billingAddressId: $data['billingAddressId'] ?? null,
            carrierId: $data['carrierId'] ?? null,
            identityCompleted: $data['identityCompleted'] ?? false,
            addressCompleted: $data['addressCompleted'] ?? false,
            deliveryCompleted: $data['deliveryCompleted'] ?? false,
            paymentCompleted: $data['paymentCompleted'] ?? false,
        );
    }
}
