<?php

declare(strict_types=1);

namespace App\Tests\Components\Checkout;

use App\Entity\Carrier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
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
