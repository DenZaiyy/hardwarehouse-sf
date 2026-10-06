<?php

declare(strict_types=1);

namespace App\Tests\Components\Checkout;

use App\DTO\Checkout\CheckoutState;
use App\Entity\Carrier;
use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Enum\PaymentMethod;
use App\Tests\Support\CreatesShopEntities;
use App\Tests\Support\FakesCatalogApi;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * Les contraintes des objets du tunnel s'appliquent bien aux actions du composant : une saisie
 * invalide est refusée (422) et le composant se réaffiche avec les erreurs au lieu d'enregistrer.
 */
final class CheckoutComponentTest extends WebTestCase
{
    use CreatesShopEntities;
    use FakesCatalogApi;
    use InteractsWithLiveComponents;

    private const array IDENTITY = ['firstName' => 'Jean', 'lastName' => 'Dupont', 'email' => 'jean.dupont@example.com'];
    private const array ADDRESS = [
        'label' => 'Domicile',
        'firstName' => 'Jean',
        'lastName' => 'Dupont',
        'address1' => '12 rue des Essais',
        'postcode' => '68100',
        'city' => 'Mulhouse',
        'country' => 'FR',
    ];

    public function testValidGuestIdentityIsAccepted(): void
    {
        $checkout = $this->guestCheckout();

        $checkout->submitForm(['checkout' => self::IDENTITY], 'saveGuest');

        self::assertTrue($checkout->response()->isSuccessful());
    }

    public function testMalformedGuestEmailIsRejected(): void
    {
        $checkout = $this->guestCheckout();

        $this->expectException(UnprocessableEntityHttpException::class);
        $checkout->submitForm(['checkout' => ['email' => 'jean.dupont'] + self::IDENTITY], 'saveGuest');
    }

    public function testGuestAddressIsSaved(): void
    {
        $checkout = $this->guestCheckout();
        $checkout->submitForm(['checkout' => self::IDENTITY], 'saveGuest');

        // Les étapes changent de type de formulaire : sous des noms différents, les champs de l'adresse
        // n'étaient plus reliés au composant et l'adresse arrivait vide sur le serveur
        $checkout->submitForm(['checkout' => self::ADDRESS], 'saveAddress');

        $summary = $checkout->render()->crawler()->text();
        self::assertStringContainsString('12 rue des Essais', $summary);
        self::assertStringContainsString('68100 Mulhouse', $summary);
    }

    public function testOverlongPostcodeIsRejected(): void
    {
        $checkout = $this->guestCheckout();
        $checkout->submitForm(['checkout' => self::IDENTITY], 'saveGuest');

        $this->expectException(UnprocessableEntityHttpException::class);
        $checkout->submitForm(['checkout' => ['postcode' => '68100-68100'] + self::ADDRESS], 'saveAddress');
    }

    public function testGuestIdentityFormIsLabelledInFrench(): void
    {
        $form = $this->guestCheckout()->render()->crawler()->filter('form');

        self::assertSame('Prénom', $form->filter('label[for="checkout_firstName"]')->text());
        self::assertSame('Nom', $form->filter('label[for="checkout_lastName"]')->text());
        self::assertSame('E-mail', $form->filter('label[for="checkout_email"]')->text());
        // Civilité facultative : M. ou Mme, sans la case « None » qu'ajoute un choix non obligatoire
        self::assertSame(['M.', 'Mme'], $form->filter('input[name="checkout[title]"] + label')->each(static fn (Crawler $label): string => $label->text()));
    }

    public function testAddressFormListsCountriesByName(): void
    {
        $checkout = $this->guestCheckout();
        $checkout->submitForm(['checkout' => self::IDENTITY], 'saveGuest');

        $form = $checkout->render()->crawler()->filter('form');

        self::assertSame('Code postal', $form->filter('label[for="checkout_postcode"]')->text());
        $countries = $form->filter('#checkout_country option')->each(static fn (Crawler $option): string => $option->text());
        self::assertContains('France', $countries);
        self::assertContains('Royaume-Uni', $countries);
    }

    public function testDeliveryStepReopensWithTheChosenCarrier(): void
    {
        $checkout = $this->guestCheckout();
        $carrier = (new Carrier())->setName('Colissimo')->setPrice('4.90');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($carrier);
        $entityManager->flush();

        $checkout->submitForm(['checkout' => self::IDENTITY], 'saveGuest');
        $checkout->submitForm(['checkout' => self::ADDRESS], 'saveAddress');
        $checkout->call('saveDeliveryChoice', ['carrierId' => $carrier->getId()]);
        $checkout->call('editDelivery');

        $radio = $checkout->render()->crawler()->filter(\sprintf('input[type="radio"][value="%d"]', $carrier->getId()));
        self::assertNotNull($radio->attr('checked'));
    }

    public function testPaymentStepOffersCardAndPaypal(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();

        $page = $this->readyToPay($client, $this->carrier('Colissimo'), $this->cartWith($this->apiHasProduct($slug), $slug))->render()->crawler();

        $methods = $page->filter('button[data-live-action-param="selectPaymentMethod"]');
        self::assertSame(['card', 'paypal'], $methods->each(static fn (Crawler $button): string => (string) $button->attr('data-live-method-param')));
        self::assertSame(['Carte bancaire', 'PayPal'], $methods->each(static fn (Crawler $button): string => trim($button->text())));
        // Carte bancaire présélectionnée, comme sur la maquette
        self::assertSame(['true', 'false'], $methods->each(static fn (Crawler $button): string => (string) $button->attr('aria-pressed')));
        // 449,90 € HT, soit 539,88 € TTC, et 4,90 € de port
        self::assertStringContainsString('544,78', $page->filter('button[data-live-action-param="processPayment"]')->text());
    }

    public function testPayButtonIsDisabledWhileTheRequestRuns(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();

        $page = $this->readyToPay($client, $this->carrier('Colissimo'), $this->cartWith($this->apiHasProduct($slug), $slug))->render()->crawler();

        // Une action data-loading inconnue (« attr ») lève une erreur JavaScript qui bloque toutes les
        // actions du composant : ni « Payer » ni le choix de PayPal n'atteignaient le serveur
        self::assertSame('addAttribute(disabled)', $page->filter('button[data-live-action-param="processPayment"]')->attr('data-loading'));
    }

    public function testUnknownPaymentMethodIsIgnored(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $checkout = $this->readyToPay($client, $this->carrier('Colissimo'), $this->cartWith($this->apiHasProduct($slug), $slug));

        $checkout->call('selectPaymentMethod', ['method' => 'paypal']);
        // Valeur envoyée par le navigateur, hors de la liste proposée
        $checkout->call('selectPaymentMethod', ['method' => 'virement']);

        self::assertSame('true', $checkout->render()->crawler()->filter('button[data-live-method-param="paypal"]')->attr('aria-pressed'));
    }

    public function testPaymentIsNotStartedWhenTheCartChangedSinceItWasFilled(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        // Produit désactivé depuis son ajout au panier
        $productId = $this->apiHasProduct($slug, active: false);

        $checkout = $this->readyToPay($client, $this->carrier('Colissimo'), $this->cartWith($productId, $slug));
        $checkout->call('processPayment');

        // Pas de redirection vers Stripe : le composant se réaffiche avec le message
        self::assertTrue($checkout->response()->isSuccessful());
        self::assertStringContainsString("n'est plus disponible et a été retiré de votre panier", $checkout->render()->crawler()->text());
    }

    public function testPaymentIsNotStartedWhenTheCarrierWasRemoved(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug);
        $remaining = $this->carrier('Colissimo');
        $removed = $this->carrier('Chronopost');
        $removedId = $removed->getId();
        $this->entityManager()->remove($removed);
        $this->entityManager()->flush();

        $checkout = $this->readyToPay($client, (int) $removedId, $this->cartWith($productId, $slug));
        $checkout->call('processPayment');

        self::assertTrue($checkout->response()->isSuccessful());
        $page = $checkout->render()->crawler();
        self::assertStringContainsString("Le transporteur choisi n'est plus disponible", $page->text());
        // L'étape de livraison est rouverte : le client choisit un autre transporteur
        self::assertCount(1, $page->filter(\sprintf('input[type="radio"][value="%d"]', $remaining->getId())));
    }

    public function testPaymentOfAnEmptyCartLeadsBackToTheCart(): void
    {
        $client = static::createClient();

        // Panier vidé entre-temps, depuis un autre onglet ou par la revérification précédente
        $checkout = $this->readyToPay($client, $this->carrier('Colissimo'), cartToken: null);
        $checkout->call('processPayment');

        self::assertTrue($checkout->response()->isRedirect());
        self::assertStringEndsWith('/cart', (string) $checkout->response()->headers->get('Location'));
    }

    public function testRefusedStripeSessionCancelsTheOrder(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $checkout = $this->readyToPay($client, $this->carrier('Colissimo'), $this->cartWith($this->apiHasProduct($slug), $slug));

        // Sans clé (phpunit.dist.xml), Stripe refuse la session, comme pour un moyen de paiement non activé
        $checkout->call('processPayment');

        self::assertTrue($checkout->response()->isSuccessful());
        self::assertStringContainsString("Le paiement n'a pas pu démarrer", $checkout->render()->crawler()->text());
        // La commande ne sera jamais payée : elle ne reste pas en attente
        $orders = $this->entityManager()->getRepository(Order::class)->findBy(['userFullNameSnapshot' => 'Jean Dupont'], ['id' => 'DESC'], 1);
        self::assertSame(OrderStatus::CANCELLED, $orders[0]->getStatus());
    }

    public function testOrderRecordsThePaymentMethodChosenInTheShop(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $checkout = $this->readyToPay($client, $this->carrier('Colissimo'), $this->cartWith($this->apiHasProduct($slug), $slug));

        $checkout->call('selectPaymentMethod', ['method' => 'paypal']);
        $checkout->call('processPayment');

        // La session Stripe ne propose que ce moyen : c'est celui avec lequel la commande est payée
        $orders = $this->entityManager()->getRepository(Order::class)->findBy(['userFullNameSnapshot' => 'Jean Dupont'], ['id' => 'DESC'], 1);
        self::assertSame(PaymentMethod::PAYPAL, $orders[0]->getPaymentMethod());
    }

    /** Tunnel rempli jusqu'au paiement par un visiteur, dont le panier est rangé sous ce jeton. */
    private function readyToPay(KernelBrowser $client, Carrier|int $carrier, ?string $cartToken): TestLiveComponent
    {
        $session = ['checkout_state' => (new CheckoutState(
            currentStep: 4,
            identityMode: 'guest',
            identity: self::IDENTITY,
            deliveryAddress: self::ADDRESS,
            carrierId: $carrier instanceof Carrier ? $carrier->getId() : $carrier,
            identityCompleted: true,
            addressCompleted: true,
            deliveryCompleted: true,
        ))->toArray()];
        if (null !== $cartToken) {
            $session['cart_session_token'] = $cartToken;
        }
        $this->prepareSession($client, $session);

        $checkout = $this->createLiveComponent('Checkout:CheckoutComponent', client: $client);
        $this->mountOutsideOfARequest();

        return $checkout;
    }

    /** @return string le jeton de session du panier */
    private function cartWith(string $productId, string $slug): string
    {
        $token = bin2hex(random_bytes(16));
        $this->createGuestCart($token, $productId, $slug, 1, '449.90');

        return $token;
    }

    private function carrier(string $name): Carrier
    {
        $carrier = (new Carrier())->setName($name)->setPrice('4.90');
        $this->entityManager()->persist($carrier);
        $this->entityManager()->flush();

        return $carrier;
    }

    /** @param array<string, mixed> $values */
    private function prepareSession(KernelBrowser $client, array $values): void
    {
        /** @var SessionFactoryInterface $factory */
        $factory = static::getContainer()->get('session.factory');
        $session = $factory->createSession();
        foreach ($values as $key => $value) {
            $session->set($key, $value);
        }
        $session->save();

        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
    }

    private function mountOutsideOfARequest(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get(RequestStack::class)->push($request);
    }

    private function guestCheckout(): TestLiveComponent
    {
        $client = static::createClient();

        // Le premier montage du composant a lieu hors requête HTTP : mount() lit pourtant l'état du
        // tunnel en session. Les actions suivantes passent par le client et sa propre session.
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get(RequestStack::class)->push($request);

        $checkout = $this->createLiveComponent('Checkout:CheckoutComponent', client: $client);
        $checkout->call('chooseGuest');

        return $checkout;
    }
}
