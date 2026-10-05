<?php

declare(strict_types=1);

namespace App\Tests\Components\Checkout;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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

        $checkout->submitForm(['guest_identity' => self::IDENTITY], 'saveGuest');

        self::assertTrue($checkout->response()->isSuccessful());
    }

    public function testMalformedGuestEmailIsRejected(): void
    {
        $checkout = $this->guestCheckout();

        $this->expectException(UnprocessableEntityHttpException::class);
        $checkout->submitForm(['guest_identity' => ['email' => 'jean.dupont'] + self::IDENTITY], 'saveGuest');
    }

    public function testOverlongPostcodeIsRejected(): void
    {
        $checkout = $this->guestCheckout();
        $checkout->submitForm(['guest_identity' => self::IDENTITY], 'saveGuest');

        $this->expectException(UnprocessableEntityHttpException::class);
        $checkout->submitForm(['checkout_address' => ['postcode' => '68100-68100'] + self::ADDRESS], 'saveAddress');
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
