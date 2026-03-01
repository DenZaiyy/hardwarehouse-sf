<?php

namespace App\Tests;

use App\Entity\ResetPasswordRequest;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Event\MessageEvent;

class RegistrationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Ensure we have a clean database
        $container = static::getContainer();

        /** @var EntityManager $em */
        $em = $container->get('doctrine')->getManager();
        $this->userRepository = $container->get(UserRepository::class);
        $resetPasswordRequestRepository = $em->getRepository(ResetPasswordRequest::class);

        // Remove any existing reset password requests first (foreign key constraint)
        foreach ($resetPasswordRequestRepository->findAll() as $request) {
            $em->remove($request);
        }

        foreach ($this->userRepository->findAll() as $user) {
            $em->remove($user);
        }

        $em->flush();
    }

    public function testRegister(): void
    {
        // Register a new user
        $this->client->request('GET', '/fr/inscription');
        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('S\'inscrire');

        $this->client->submitForm('Crée mon compte', [
            'registration_form[username]' => 'Username',
            'registration_form[email]' => 'me@example.com',
            'registration_form[plainPassword][first]' => 'PasswordTest168!',
            'registration_form[plainPassword][second]' => 'PasswordTest168!',
            'registration_form[agreeTerms]' => true,
        ]);

        // Ensure the response redirects after submitting the form, the user exists, and is not verified
        self::assertCount(1, $this->userRepository->findAll());
        $user = $this->userRepository->findAll()[0];
        self::assertFalse($user->isVerified());

        // Ensure the verification email was sent (registration sends 2: verification + admin notification).
        // With Symfony Mailer + Messenger, each send produces 2 MessageEvents: queued=true (when dispatched
        // to Messenger bus) and queued=false (when actually delivered by the transport). assertEmailCount
        // counts only the delivered (non-queued) emails.
        self::assertEmailCount(2);

        // Use only the delivered (non-queued) events to access rendered HTML bodies.
        $sentMessages = array_values(array_map(
            fn (MessageEvent $e) => $e->getMessage(),
            array_filter(self::getMailerEvents(), fn (MessageEvent $e) => !$e->isQueued())
        ));
        self::assertCount(2, $sentMessages);

        /** @var TemplatedEmail $verificationEmail */
        $verificationEmail = $sentMessages[0];
        self::assertEmailAddressContains($verificationEmail, 'from', 'support@hardwarehouse.fr');
        self::assertEmailAddressContains($verificationEmail, 'to', 'me@example.com');
        // The expiry text is locale-dependent (e.g. "1 hour" in EN, "1 heure" in FR).
        // Check only the locale-independent prefix.
        self::assertEmailHtmlBodyContains($verificationEmail, 'This link will expire in');

        // Login the new user
        $this->client->followRedirect();
        $this->client->loginUser($user);

        // Get the verification link from the email.
        // The URL locale prefix matches the registration request locale (/fr/inscription → fr).
        $messageBody = $verificationEmail->getHtmlBody();
        self::assertIsString($messageBody);

        preg_match('#(http://localhost/(?:en|fr)/verify/email[^"<\s]*)#', $messageBody, $resetLink);
        self::assertArrayHasKey(1, $resetLink, 'Verification link not found in email body.');

        // "Click" the link and see if the user is verified
        $this->client->request('GET', $resetLink[1]);
        $this->client->followRedirect();

        self::assertTrue(static::getContainer()->get(UserRepository::class)->findAll()[0]->isVerified());
    }
}
