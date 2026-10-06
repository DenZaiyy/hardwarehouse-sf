<?php

declare(strict_types=1);

namespace App\Service\Checkout;

use App\DTO\Checkout\GuestIdentityData;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Compte créé depuis le tunnel quand le visiteur saisit un mot de passe : même vérification de
 * l'adresse e-mail qu'à l'inscription. Le pseudonyme reprend le prénom et le nom.
 */
final readonly class GuestAccountCreator
{
    private const int USERNAME_MAX_LENGTH = 20;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailVerifier $emailVerifier,
        private TranslatorInterface $translator,
        #[Autowire('%env(FROM_EMAIL)%')]
        private string $supportEmail,
    ) {
    }

    public function create(GuestIdentityData $identity): User
    {
        $user = (new User())
            ->setEmail((string) $identity->email)
            ->setUsername($this->availableUsername(trim($identity->firstName.' '.$identity->lastName), (string) $identity->email))
            ->setIsVerified(false);
        $user->setPassword($this->passwordHasher->hashPassword($user, (string) $identity->password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->emailVerifier->sendEmailConfirmation(
            'app_verify_email',
            $user,
            new TemplatedEmail()
                ->from(new Address($this->supportEmail, 'HardWareHouse - Support'))
                ->to((string) $user->getEmail())
                ->subject($this->translator->trans('user.register.email.confirm.subject'))
                ->htmlTemplate('security/registration/confirmation_email.html.twig')
        );

        return $user;
    }

    /** « Jean Dupont », puis « Jean Dupont 2 » s'il est pris ; 20 caractères au plus, 3 au moins. */
    private function availableUsername(string $name, string $email): string
    {
        $base = mb_strlen($name) >= 3 ? $name : strstr($email, '@', true).'-client';

        for ($number = 1; ; ++$number) {
            $suffix = 1 === $number ? '' : ' '.$number;
            $candidate = trim(mb_substr($base, 0, self::USERNAME_MAX_LENGTH - mb_strlen($suffix))).$suffix;

            if (null === $this->userRepository->findOneBy(['userName' => $candidate])) {
                return $candidate;
            }
        }
    }
}
