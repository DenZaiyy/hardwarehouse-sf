<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;

readonly class MailerService
{
    public function __construct(
        private RequestStack $requestStack,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $adminEmail = 'grischko.kevin@gmail.com',
        private string $fromEmail = 'noreply@denz.ovh',
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
     * @param array<string, mixed> $context
     */
    private function sendTemplatedEmail(string $to, string $subject, string $template, array $context = [], ?string $from = null): void
    {
        try {
            $email = new TemplatedEmail()
                ->from($from ?? $this->fromEmail)
                ->to($to)
                ->subject($subject)
                ->htmlTemplate($template)
                ->context($context);
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
