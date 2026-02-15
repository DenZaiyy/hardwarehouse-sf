<?php

namespace App\Controller\User;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use App\Service\ImageUploadService;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly EmailVerifier $emailVerifier,
        private readonly MailerService $mailerService,
        private readonly EntityManagerInterface $em,
        private readonly ImageUploadService $uploadService,
    ) {
    }

    #[Route(path: ['en' => '/register', 'fr' => '/inscription'], name: 'app.register', options: ['sitemap' => true])]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, Security $security, TranslatorInterface $translator): Response
    {
        if ($this->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('homepage');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            /** @var ?UploadedFile $avatar */
            $avatar = $form->get('avatar')->getData();
            if ($avatar instanceof UploadedFile) {
                $uploadedFile = $this->uploadService->upload($avatar, $user->getUsername(), type: 'avatar');

                $user->setAvatar($uploadedFile);
            }

            $this->em->persist($user);
            $this->em->flush();

            // generate a signed url and email it to the user
            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                new TemplatedEmail()
                    ->from(new Address('support@denz.ovh', 'HardWareHouse - Support'))
                    ->to((string) $user->getEmail())
                    ->subject($translator->trans('user.register.email.confirm.subject'))
                    ->htmlTemplate('security/registration/confirmation_email.html.twig')
            );

            $this->addFlash('success', $translator->trans('user.registration.flash.success'));
            $this->redirectToRoute('homepage');

            $this->mailerService->sendAdminNotification('Inscription', sprintf("Un nouvel utilisateur c'est inscrit sur le site : %s (%s)", $user->getUsername(), $user->getEmail()));
            $security->login($user, 'form_login', 'main');

            return $this->redirectToRoute('homepage');
        }

        return $this->render('security/registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    /**
     * @throws ORMException
     */
    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            /** @var User $user */
            $user = $this->getUser();

            // Vérification de l'email - cela met à jour isVerified=true et persiste
            $this->emailVerifier->handleEmailConfirmation($request, $user);
            $this->em->refresh($user);
            $welcomeSent = $this->mailerService->sendWelcomeMail((string) $user->getEmail());

            if ($welcomeSent) {
                $this->addFlash('success', 'Votre adresse email à été vérifiée. Un mail de bienvenue vous a été envoyé.');
            } else {
                $this->addFlash('success', 'Votre adresse email a été vérifiée.');
                $this->addFlash('warning', 'L\'email de bienvenue n\'a pas pu être envoyé.');
            }
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app.register');
        }

        return $this->redirectToRoute('homepage');
    }
}
