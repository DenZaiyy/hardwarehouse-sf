<?php

namespace App\DTO\Checkout;

use App\Enum\PaymentMethod;

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
 *     billingAddress?: DeliveryAddress|null,
 *     billingAddressId?: int|null,
 *     billingSameAsDelivery?: bool,
 *     billingCompleted?: bool,
 *     carrierId?: int|null,
 *     showAddressForm?: bool,
 *     showBillingAddressForm?: bool,
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
     * @param DeliveryAddress|null  $billingAddress
     */
    public function __construct(
        public int $currentStep = 1,
        public string $identityMode = 'choice',
        public ?array $identity = null,
        public ?array $deliveryAddress = null,
        public ?int $deliveryAddressId = null,
        public ?array $billingAddress = null,
        public ?int $billingAddressId = null,
        public bool $billingSameAsDelivery = true,
        public bool $billingCompleted = true,
        public ?int $carrierId = null,
        public bool $showAddressForm = false,
        public bool $showBillingAddressForm = false,
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
            'billingAddress' => $this->billingAddress,
            'billingAddressId' => $this->billingAddressId,
            'billingSameAsDelivery' => $this->billingSameAsDelivery,
            'billingCompleted' => $this->billingCompleted,
            'carrierId' => $this->carrierId,
            'showAddressForm' => $this->showAddressForm,
            'showBillingAddressForm' => $this->showBillingAddressForm,
            'paymentMethod' => $this->paymentMethod,
            'identityCompleted' => $this->identityCompleted,
            'addressCompleted' => $this->addressCompleted,
            'deliveryCompleted' => $this->deliveryCompleted,
            'paymentCompleted' => $this->paymentCompleted,
        ];
    }

    /**
     * Moyen choisi par le client ; la carte est présélectionnée, comme sur la maquette. Une valeur
     * inconnue (enregistrée avant l'ajout de PayPal, par exemple « stripe ») revient aussi à la carte.
     */
    public function selectedPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::tryFrom($this->paymentMethod ?? '') ?? PaymentMethod::CARD;
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
            billingAddress: $data['billingAddress'] ?? null,
            billingAddressId: $data['billingAddressId'] ?? null,
            billingSameAsDelivery: $data['billingSameAsDelivery'] ?? true,
            billingCompleted: $data['billingCompleted'] ?? true,
            carrierId: $data['carrierId'] ?? null,
            showAddressForm: $data['showAddressForm'] ?? false,
            showBillingAddressForm: $data['showBillingAddressForm'] ?? false,
            paymentMethod: $data['paymentMethod'] ?? null,
            identityCompleted: $data['identityCompleted'] ?? false,
            addressCompleted: $data['addressCompleted'] ?? false,
            deliveryCompleted: $data['deliveryCompleted'] ?? false,
            paymentCompleted: $data['paymentCompleted'] ?? false,
        );
    }
}
