<?php

declare(strict_types=1);

namespace App\Controller\User\Profile;

use App\Entity\User;
use App\Form\User\ChangePasswordFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile', name: "profile.")]
#[IsGranted('ROLE_USER', message: 'You do not have access to this page.', statusCode: Response::HTTP_FORBIDDEN)]
class ProfileController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(Request $request, UserPasswordHasherInterface $hasher, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('This user does not have access to this section.');
        }

        $passwordForm = $this->createForm(ChangePasswordFormType::class);
        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $currentPassword = $passwordForm->get('password')->getData();

            if ($hasher->hashPassword($user, $currentPassword)) {
                $newPassword = $passwordForm->get('plainPassword')->getData();
                $user->setPassword($hasher->hashPassword($user, $newPassword));
                $user->setUpdatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));

                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Your password has been changed.');
            } else {
                $this->addFlash('error', 'Your password is incorrect.');
            }

            return $this->redirectToRoute('profile.index');
        }

        return $this->render('user/profile/index.html.twig', [
            'user' => $user,
            'passwordForm' => $passwordForm->createView(),
        ]);
    }
}
