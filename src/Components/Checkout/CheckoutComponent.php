<?php

namespace App\Components\Checkout;

use App\DTO\Checkout\AddressData;
use App\DTO\Checkout\CheckoutState;
use App\DTO\Checkout\DeliveryChoiceData;
use App\DTO\Checkout\GuestIdentityData;
use App\Entity\Address;
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

    /**
     * Chaque étape a son type de formulaire, mais ComponentWithFormTrait retient le nom du premier
     * formulaire (LiveProp formName) et y rattache les saisies : sous des noms différents, les champs
     * des étapes suivantes n'étaient plus reliés au composant et arrivaient vides sur le serveur.
     */
    private const string FORM_NAME = 'checkout';

    /**
     * Ce que la revérification du panier a corrigé au moment de payer : affiché au-dessus du paiement
     * le temps du rendu qui suit l'action (ce n'est pas une LiveProp).
     *
     * @var list<string>
     */
    public array $paymentNotices = [];

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

    /**
     * @return list<array{
     *     id: int|null,
     *     label: string|null,
     *     firstName: string|null,
     *     lastName: string|null,
     *     address1: string|null,
     *     postcode: string|null,
     *     city: string|null,
     *     country: string|null,
     *     isDefault: bool|null
     * }>
     */
    public function getSavedDeliveryAddresses(): array
    {
        $user = $this->getUser();

        if (!$user) {
            return [];
        }

        $addresses = $this->addressManager->getUserAddressesByType($user, AddressType::DELIVERY);

        return array_values(array_map($this->mapAddressToArray(...), $addresses));
    }

    /**
     * @return array{
     *     id: int|null,
     *     label: string|null,
     *     firstName: string|null,
     *     lastName: string|null,
     *     address1: string|null,
     *     postcode: string|null,
     *     city: string|null,
     *     country: string|null,
     *     isDefault: bool|null
     * }
     */
    private function mapAddressToArray(Address $address): array
    {
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
    }

    public function shouldShowAddressSelection(): bool
    {
        $state = $this->getState();
        $user = $this->getUser();

        return 2 === $state->currentStep
            && $user instanceof User
            && \count($this->addressManager->getUserAddressesByType($user, AddressType::DELIVERY)) > 0
            && !$state->showAddressForm;
    }

    public function shouldShowAddressForm(): bool
    {
        $state = $this->getState();
        $user = $this->getUser();

        return 2 === $state->currentStep
            && (!$user instanceof User
                || 0 === \count($this->addressManager->getUserAddressesByType($user, AddressType::DELIVERY))
                || $state->showAddressForm);
    }

    /**
     * @return list<array{
     *     id: int|null,
     *     label: string|null,
     *     firstName: string|null,
     *     lastName: string|null,
     *     address1: string|null,
     *     postcode: string|null,
     *     city: string|null,
     *     country: string|null,
     *     isDefault: bool|null
     * }>
     */
    public function getSavedBillingAddresses(): array
    {
        $user = $this->getUser();

        if (!$user) {
            return [];
        }

        $addresses = $this->addressManager->getUserBillingAddresses($user);

        return array_values(array_map($this->mapAddressToArray(...), $addresses));
    }

    public function shouldShowBillingAddressSelection(): bool
    {
        $state = $this->getState();
        $user = $this->getUser();

        return $state->addressCompleted
            && !$state->billingSameAsDelivery
            && !$state->billingCompleted
            && $user instanceof User
            && \count($this->addressManager->getUserBillingAddresses($user)) > 0
            && !$state->showBillingAddressForm;
    }

    public function shouldShowBillingAddressForm(): bool
    {
        $state = $this->getState();
        $user = $this->getUser();

        return $state->addressCompleted
            && !$state->billingSameAsDelivery
            && !$state->billingCompleted
            && (!$user instanceof User
                || 0 === \count($this->addressManager->getUserBillingAddresses($user))
                || $state->showBillingAddressForm);
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
            return $this->createGuestForm($state);
        }

        if (2 === $state->currentStep && $this->shouldShowAddressForm()) {
            return $this->createAddressForm($state);
        }

        if ($this->shouldShowBillingAddressForm()) {
            return $this->createBillingAddressForm($state);
        }

        if (3 === $state->currentStep) {
            return $this->createDeliveryForm($state);
        }

        return $this->createDefaultForm();
    }

    private function createGuestForm(CheckoutState $state): FormInterface
    {
        $identity = $state->identity;
        $data = new GuestIdentityData();
        $data->title = $identity['title'] ?? null;
        $data->firstName = $identity['firstName'] ?? null;
        $data->lastName = $identity['lastName'] ?? null;
        $data->email = $identity['email'] ?? null;

        return $this->formFactory->createNamed(self::FORM_NAME, GuestIdentityType::class, $data, [
            'csrf_protection' => false,
        ]);
    }

    private function createAddressForm(CheckoutState $state): FormInterface
    {
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

        return $this->formFactory->createNamed(self::FORM_NAME, CheckoutAddressType::class, $data, [
            'csrf_protection' => false,
        ]);
    }

    private function createBillingAddressForm(CheckoutState $state): FormInterface
    {
        $identity = $state->identity;
        $billingAddress = $state->billingAddress;

        $data = new AddressData();
        $data->label = $billingAddress['label'] ?? 'Facturation';
        $data->firstName = $billingAddress['firstName'] ?? ($identity['firstName'] ?? null);
        $data->lastName = $billingAddress['lastName'] ?? ($identity['lastName'] ?? null);
        $data->address1 = $billingAddress['address1'] ?? null;
        $data->postcode = $billingAddress['postcode'] ?? null;
        $data->city = $billingAddress['city'] ?? null;
        $data->country = $billingAddress['country'] ?? 'FR';

        return $this->formFactory->createNamed(self::FORM_NAME, CheckoutAddressType::class, $data, [
            'csrf_protection' => false,
        ]);
    }

    private function createDeliveryForm(CheckoutState $state): FormInterface
    {
        $data = new DeliveryChoiceData();
        $data->carrierId = $state->carrierId;

        return $this->formFactory->createNamed(self::FORM_NAME, DeliveryChoiceType::class, $data, [
            'carriers' => $this->deliveryManager->getCarriers($state),
            'csrf_protection' => false,
        ]);
    }

    private function createDefaultForm(): FormInterface
    {
        return $this->formFactory->createNamed(self::FORM_NAME, GuestIdentityType::class, new GuestIdentityData(), [
            'csrf_protection' => false,
        ]);
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
        $this->resetForm();
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

        // Avance automatiquement à l'étape suivante si l'adresse est complétée
        if ($state->addressCompleted) {
            $state->currentStep = 3;
            $state->showAddressForm = false; // Cache le formulaire d'adresse
        }

        $this->stateManager->saveState($state);

        // Force form re-instantiation pour l'étape suivante
        $this->resetForm();
    }

    #[LiveAction]
    public function useNewAddressForm(): RedirectResponse
    {
        $state = $this->getState();
        $state->deliveryAddressId = null;
        $state->showAddressForm = true;

        $this->stateManager->saveState($state);

        // Force une redirection pour rafraîchir le composant avec un nouveau CSRF token
        return new RedirectResponse($this->urlGenerator->generate('checkout.index'));
    }

    #[LiveAction]
    public function toggleBillingSameAsDelivery(#[LiveArg] bool $sameAsDelivery): void
    {
        $state = $this->addressManager->saveBillingSameAsDelivery($this->getState(), $sameAsDelivery);
        $this->stateManager->saveState($state);

        $this->resetForm();
    }

    #[LiveAction]
    public function saveBillingAddress(): void
    {
        $this->submitForm();

        /** @var AddressData $data */
        $data = $this->getForm()->getData();
        $state = $this->getState();
        $user = $this->getUser();

        if ($user instanceof User) {
            $hasExisting = \count($this->addressManager->getUserBillingAddresses($user)) > 0;

            $state = $this->addressManager->createBillingAddressForUser(
                $state,
                $user,
                $data,
                !$hasExisting
            );
        } else {
            $state = $this->addressManager->saveGuestBillingAddress($state, $data);
        }

        $this->stateManager->saveState($state);
        $this->resetForm();
    }

    #[LiveAction]
    public function useNewBillingAddressForm(): RedirectResponse
    {
        $state = $this->getState();
        $state->billingAddressId = null;
        $state->showBillingAddressForm = true;

        $this->stateManager->saveState($state);

        return new RedirectResponse($this->urlGenerator->generate('checkout.index'));
    }

    #[LiveAction]
    public function saveDeliveryChoice(#[LiveArg] int $carrierId = 0): void
    {
        $state = $this->deliveryManager->saveCarrier($this->getState(), $carrierId);
        $this->stateManager->saveState($state);

        if ($state->deliveryCompleted) {
            $this->resetForm();
        }
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

        // Force form re-instantiation to refresh CSRF token
        $this->resetForm();
    }

    #[LiveAction]
    public function selectBillingAddress(#[LiveArg] int $addressId): void
    {
        $user = $this->getUser();

        if (!$user) {
            return;
        }

        $address = $this->addressManager->findOwnedBillingAddressById($user, $addressId);

        if (!$address) {
            return;
        }

        $state = $this->addressManager->saveSelectedBillingAddress($this->getState(), $address);
        $this->stateManager->saveState($state);

        $this->resetForm();
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
        $this->resetForm();
    }

    #[LiveAction]
    public function editAddress(): void
    {
        $state = $this->getState();

        if ($state->identityCompleted) {
            $state->currentStep = 2;
            $this->stateManager->saveState($state);
            $this->resetForm();
        }
    }

    #[LiveAction]
    public function editDelivery(): void
    {
        $state = $this->getState();

        if ($state->identityCompleted && $state->addressCompleted) {
            $state->currentStep = 3;
            $this->stateManager->saveState($state);
            // Sans réinitialisation, le formulaire de l'étape rouverte serait soumis avec les valeurs
            // de l'étape précédente : le transporteur choisi ne serait plus coché
            $this->resetForm();
        }
    }

    #[LiveAction]
    public function selectPaymentMethod(#[LiveArg] string $method): void
    {
        $state = $this->getState();

        if ($state->identityCompleted && $state->addressCompleted && $state->deliveryCompleted) {
            $state->paymentMethod = empty($method) ? null : $method;
            $state->currentStep = 4;

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

    /**
     * @return array<string, string>
     */
    public function getAvailablePaymentMethods(): array
    {
        return [
            'stripe' => 'Carte bancaire (Stripe)',
            // 'paypal' => 'PayPal',
            // 'bank_transfer' => 'Virement bancaire',
        ];
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
     * @return array<string, array{productId: string, quantity: int, remaining_stock: int, category: string, name: string, price_ht: float, price_ttc: float, effective_ht: float, effective_ttc: float, imageUrl: string, slug: string, discount_price: float|null, discount_amount: float|null, promote: bool}>
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
     * Get cart totals including carrier costs.
     *
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
                return $this->extractPriceFromLabel($carrier['label']);
            }
        }

        return 0.0;
    }

    private function extractPriceFromLabel(string $label): float
    {
        // Extract price from label (format: "Name - X,XX €")
        if (preg_match('/(\d+(?:,\d+)?)\s*€/', $label, $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }

        return 0.0;
    }

    /**
     * @return RedirectResponse|null null quand le panier ou le transporteur a changé depuis qu'ils ont
     *                               été choisis : le composant se réaffiche avec paymentNotices
     */
    #[LiveAction]
    public function processPayment(): ?RedirectResponse
    {
        $state = $this->getState();

        // Verify checkout is complete
        if (!$state->identityCompleted || !$state->addressCompleted || !$state->deliveryCompleted) {
            throw new \LogicException('Checkout is not complete');
        }

        // Panier vidé entre-temps (autre onglet, revérification précédente) : retour au panier
        if ([] === $this->getCartItems()) {
            return new RedirectResponse($this->urlGenerator->generate('cart.index'));
        }

        // Le catalogue et les transporteurs ont pu changer depuis que le panier a été rempli : le client
        // paie ce qu'il a vu, sinon il en est prévenu et aucune session Stripe n'est créée
        try {
            $this->paymentNotices = $this->cartService->revalidate();
        } catch (\RuntimeException $e) {
            $this->paymentNotices = [$e->getMessage()];

            return null;
        }

        if (!$this->deliveryManager->ensureCarrierStillAvailable($state)) {
            $this->stateManager->saveState($state);
            $this->resetForm();
            $this->paymentNotices[] = "Le transporteur choisi n'est plus disponible : merci d'en choisir un autre.";
        }

        if ([] !== $this->paymentNotices) {
            return null;
        }

        // Create order with PENDING status before payment
        $user = $this->getUser();
        $order = $this->orderService->createOrderFromCartAndCheckout(
            $state,
            $user instanceof User ? $user : null
        );

        // Seule cette session pourra afficher la confirmation d'une commande passée sans compte
        $this->stateManager->rememberOrderReference((string) $order->getReference());

        // Create Stripe checkout session from the order: it bills the order's own amounts
        $successUrl = $this->urlGenerator->generate('payment.success', ['reference' => $order->getReference()], UrlGeneratorInterface::ABSOLUTE_URL);
        $cancelUrl = $this->urlGenerator->generate('checkout.index', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $session = $this->stripeService->createCheckoutSession($order, $this->getSelectedCarrierLabel(), $successUrl, $cancelUrl);

        $sessionUrl = $session->url;
        if (null === $sessionUrl) {
            throw new \RuntimeException('Stripe checkout session URL is missing');
        }

        return new RedirectResponse($sessionUrl);
    }
}
