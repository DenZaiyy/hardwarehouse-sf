<?php

namespace App\Service;

use App\Entity\Order;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;

readonly class MailerService
{
    public function __construct(
        private RequestStack $requestStack,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire('%env(ADMIN_EMAIL)%')]
        private string $adminEmail = 'grischko.kevin@gmail.com',
        #[Autowire('%env(FROM_EMAIL)%')]
        private string $fromEmail = 'noreply@hardwarehouse.fr',
    ) {
    }

    public function sendWelcomeMail(string $userEmail): bool
    {
        $validatedEmail = $this->validateEmail($userEmail);
        if (!$validatedEmail) {
            $this->logger->error("L'adresse e-mail n'est pas une adresse valide", [
                'email' => $userEmail,
            ]);

            return false;
        }

        $this->sendTemplatedEmail(
            $userEmail,
            'Bienvenue',
            'emails/welcome.html.twig',
            [
                'userEmail' => $userEmail,
                'loginUrl' => $this->getBaseDomain().'/login',
            ]
        );

        $this->logger->info('Email de bienvenue envoyé', [
            'recipient' => $userEmail,
        ]);

        return true;
    }

    /**
     * Confirmation envoyée une fois la commande payée ; une commande sans compte passée avant
     * l'enregistrement de l'e-mail n'en reçoit pas.
     */
    /**
     * @param array<string, string> $attachments chemin du fichier => nom proposé au client
     */
    public function sendOrderConfirmation(Order $order, array $attachments = []): bool
    {
        $email = $order->getCustomerEmail();
        if (null === $email || !$this->validateEmail($email)) {
            $this->logger->warning('Order confirmation not sent: no valid customer email', [
                'reference' => $order->getReference(),
            ]);

            return false;
        }

        $this->sendTemplatedEmail(
            $email,
            sprintf('Confirmation de votre commande %s', $order->getReference()),
            'emails/order/confirmation.html.twig',
            ['order' => $order],
            attachments: $attachments,
        );

        return true;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function sendAdminNotification(string $subject, string $message, array $context = [], ?string $adminMail = null): bool
    {
        $validatedEmail = $this->validateEmail($adminMail ?? $this->adminEmail);
        if (!$validatedEmail) {
            $this->logger->error("L'adresse e-mail n'est pas une adresse valide", [
                'email' => $adminMail ?? $this->adminEmail,
            ]);

            return false;
        }

        $this->sendTemplatedEmail(
            $this->adminEmail,
            '[ADMIN] '.$subject,
            'emails/admin/notification.html.twig',
            [
                'message' => $message,
                'context' => $context,
                'timestamp' => new \DateTime(),
            ]
        );

        $this->logger->info('Notification admin envoyé', [
            'subject' => $subject,
            'context' => $context,
        ]);

        return true;
    }

    /**
     * @param array<string, mixed>  $context
     * @param array<string, string> $attachments chemin du fichier => nom proposé au destinataire
     */
    public function sendTemplatedEmail(string $to, string $subject, string $template, array $context = [], ?string $from = null, array $attachments = []): void
    {
        try {
            $email = new TemplatedEmail()
                ->from($from ?? $this->fromEmail)
                ->to($to)
                ->subject($subject)
                ->htmlTemplate($template)
                ->context($context);
            foreach ($attachments as $path => $name) {
                $email->attachFromPath($path, $name);
            }
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email template', [
                'error' => $e->getMessage(),
                'recipient' => $to,
                'subject' => $subject,
            ]);
        }
    }

    private function validateEmail(string $email): bool
    {
        $pattern = "/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/i";

        return (bool) preg_match($pattern, $email);
    }

    private function getBaseDomain(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return null;
        }

        return $request->getSchemeAndHttpHost();
    }
}
