<?php

declare(strict_types=1);

namespace App\Tests\Controller\User;

use App\Form\RegistrationFormType;
use App\Service\CspNonceService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegistrationFormTest extends WebTestCase
{
    public function testRegistrationPageUsesTheMockupWording(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr/inscription');

        self::assertSelectorTextSame('h1', 'Créez votre compte');
        self::assertSelectorTextSame('label[for="registration_form_avatar"]', 'Image de profil (facultatif)');
        self::assertSelectorTextContains('#registration-form', '12 à 32 caractères, dont au moins une majuscule');
        self::assertSelectorExists('input[data-toggle-password-visible-label-value="Afficher"]');
        self::assertSelectorTextSame('#registration-form button[type="submit"]', 'Créer mon compte');
    }

    public function testRefusedTermsAreExplained(): void
    {
        $client = static::createClient();
        $client->request('GET', '/fr/inscription');

        $client->submitForm('Créer mon compte', [
            'registration_form[username]' => 'jdupont',
            'registration_form[email]' => 'jean.dupont@example.com',
            'registration_form[plainPassword][first]' => 'PasswordTest168!',
            'registration_form[plainPassword][second]' => 'PasswordTest168!',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#registration-form', "Vous devez accepter les conditions générales d'utilisation.");
    }

    public function testCaptchaScriptsCarryTheCspNonce(): void
    {
        static::createClient();
        // La CSP n'autorise que les scripts portant le nonce de la requête (script-src 'nonce-…' 'strict-dynamic')
        $nonce = static::getContainer()->get(CspNonceService::class)->generate();

        $form = static::getContainer()->get('form.factory')->create(RegistrationFormType::class);

        self::assertSame($nonce, $form->get('captcha')->getConfig()->getOption('script_nonce_csp'));
    }

    public function testPageDoesNotLoadAnUnsignedCaptchaScript(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr/inscription');

        self::assertStringNotContainsString('recaptcha/api.js?render=', (string) $client->getResponse()->getContent());
    }
}
