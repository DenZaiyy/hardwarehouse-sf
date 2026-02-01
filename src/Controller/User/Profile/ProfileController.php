<?php

declare(strict_types=1);

namespace App\Controller\User\Profile;

use App\Entity\User;
use App\Form\User\ChangePasswordFormType;
use App\Form\User\UpdateInfosFormType;
use App\Service\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/profile', name: 'profile.')]
// #[IsGranted('ROLE_USER', message: 'You do not have access to this page.', statusCode: Response::HTTP_FORBIDDEN)]
class ProfileController extends AbstractController
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    #[Route('', name: 'index')]
    public function index(Request $request, UserPasswordHasherInterface $hasher, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            $this->addFlash('danger', $this->translator->trans('user.update.infos.not_logged.message'));

            return $this->redirectToRoute('app.login');
        }

        return $this->render('user/profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/infos', name: 'infos')]
    public function updateInfos(Request $request, EntityManagerInterface $entityManager, ImageUploadService $imageUploadService): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            $this->addFlash('danger', $this->translator->trans('user.update.infos.not_logged.message'));

            return $this->redirectToRoute('app.login');
        }

        $form = $this->createForm(UpdateInfosFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $data */
            $data = $form->getData();

            $email = $data->getEmail();

            if ($email !== $user->getEmail()) {
                // check if email not exists
                if ($entityManager->getRepository(User::class)->findOneBy(['email' => $email])) {
                    $this->addFlash('danger', $this->translator->trans('user.update.infos.email.already_exists'));
                }

                if (null !== $email) {
                    $user->setEmail($email);
                }
            }

            $entityManager->flush();
            $this->addFlash('success', $this->translator->trans('user.update.infos.success'));

            // Si requête Turbo, retourne des streams
            if ($request->headers->has('Turbo-Frame') || 'turbo_stream' === $request->getPreferredFormat()) {
                return $this->render('user/profile/_infos_stream.html.twig', [
                    'user' => $user,
                ], new Response('', Response::HTTP_OK, [
                    'Content-Type' => 'text/vnd.turbo-stream.html',
                ]));
            }

            return $this->redirectToRoute('profile.index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/profile/infos.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/password', name: 'password')]
    public function updatePassword(Request $request, UserPasswordHasherInterface $hasher, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            $this->addFlash('danger', $this->translator->trans('user.update.infos.not_logged.message'));

            return $this->redirectToRoute('app.login');
        }

        $form = $this->createForm(ChangePasswordFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $currentPassword */
            $currentPassword = $form->get('password')->getData();

            if ($hasher->hashPassword($user, $currentPassword)) {
                /** @var string $newPassword */
                $newPassword = $form->get('plainPassword')->getData();
                $user->setPassword($hasher->hashPassword($user, $newPassword));

                $entityManager->flush();

                $this->addFlash('success', 'Your password has been changed successfully.');

                // Si requête Turbo, retourne des streams
                if ($request->headers->has('Turbo-Frame') || 'turbo_stream' === $request->getPreferredFormat()) {
                    return $this->render('user/profile/_infos_stream.html.twig', [
                        'user' => $user,
                    ], new Response('', Response::HTTP_OK, [
                        'Content-Type' => 'text/vnd.turbo-stream.html',
                    ]));
                }
            } else {
                $this->addFlash('error', 'Your password is incorrect.');
            }

            return $this->redirectToRoute('profile.index');
        }

        return $this->render('user/profile/password.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }
}
