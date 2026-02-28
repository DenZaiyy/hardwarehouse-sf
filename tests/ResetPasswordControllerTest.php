<?php

namespace App\Tests;

use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ResetPasswordControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Ensure we have a clean database
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $this->em = $em;

        $this->userRepository = $container->get(UserRepository::class);
        $resetPasswordRequestRepository = $this->em->getRepository(ResetPasswordRequest::class);

        // Remove any existing reset password requests first (foreign key constraint)
        foreach ($resetPasswordRequestRepository->findAll() as $request) {
            $this->em->remove($request);
        }

        foreach ($this->userRepository->findAll() as $user) {
            $this->em->remove($user);
        }

        $this->em->flush();
    }

    public function testResetPasswordController(): void
    {
        // Create a test user
        $user = (new User())
            ->setUsername('me')
            ->setEmail('me@example.com')
            ->setPassword('a-test-password-that-will-be-changed-later')
        ;
        $this->em->persist($user);
        $this->em->flush();

        // Test Request reset password page
        $this->client->request('GET', '/en/reset-password');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Reset your password');

        // Submit the reset password form and test email message is queued / sent
        $this->client->submitForm('Send password reset email', [
            'reset_password_request_form[email]' => 'me@example.com',
        ]);

        // Ensure the reset password email was sent
        // Use either assertQueuedEmailCount() || assertEmailCount() depending on your mailer setup
        self::assertEmailCount(1);

        // With Symfony Mailer + Messenger, each send produces 2 MessageEvents: queued=true (dispatched to bus)
        // and queued=false (actually delivered). Use only delivered events to access the rendered HTML body.
        $sentMessages = array_values(array_map(
            fn (MessageEvent $e) => $e->getMessage(),
            array_filter(self::getMailerEvents(), fn (MessageEvent $e) => !$e->isQueued())
        ));
        self::assertCount(1, $sentMessages);

        self::assertEmailAddressContains($sentMessages[0], 'from', 'support@hardwarehouse.fr');
        self::assertEmailAddressContains($sentMessages[0], 'to', 'me@example.com');
        self::assertEmailTextBodyContains($sentMessages[0], 'This link will expire in 1 hour.');

        self::assertResponseRedirects('/en/reset-password/check-email');

        // Test check email landing page shows correct "expires at" time
        $crawler = $this->client->followRedirect();

        self::assertPageTitleContains('Password Reset Email Sent');
        self::assertStringContainsString('This link will expire in 1 hour', $crawler->html());

        // Test the link sent in the email is valid
        $messageBody = $sentMessages[0]->getHtmlBody();
        self::assertIsString($messageBody);
        preg_match('#(/en/reset-password/reset/[^"<\s]+)#', $messageBody, $resetLink);

        $this->client->request('GET', $resetLink[1]);

        self::assertResponseRedirects('/en/reset-password/reset');

        $this->client->followRedirect();

        // Test we can set a new password
        $this->client->submitForm('Reset password', [
            'change_password_form[plainPassword][first]' => 'xJ3!mK9@nP2#qR7s',
            'change_password_form[plainPassword][second]' => 'xJ3!mK9@nP2#qR7s',
        ]);

        self::assertResponseRedirects('/en/login');
        $this->client->followRedirect();

        $user = $this->userRepository->findOneBy(['email' => 'me@example.com']);

        self::assertInstanceOf(User::class, $user);

        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($passwordHasher->isPasswordValid($user, 'xJ3!mK9@nP2#qR7s'));
    }
}
