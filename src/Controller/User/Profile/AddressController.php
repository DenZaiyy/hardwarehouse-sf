<?php

declare(strict_types=1);

namespace App\Controller\User\Profile;

use App\Entity\Address;
use App\Entity\User;
use App\Form\User\UserAddressFormType;
use App\Repository\AddressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/profile/address', name: 'address.')]
#[IsGranted('ROLE_USER')]
class AddressController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $em
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(AddressRepository $repository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $addresses = $repository->findBy(['user_info' => $user]);

        return $this->render('user/address/index.html.twig', [
            'user' => $user,
            'addresses' => $addresses,
        ]);
    }

    #[Route('/add', name: 'add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $address = new Address();

        $form = $this->createForm(UserAddressFormType::class, $address);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->addAddress($address);
            $this->em->persist($address);
            $this->em->flush();
            $this->addFlash(
                'success',
                $this->translator->trans('user.address.create.success')
            );

            return $this->redirectToRoute('address.index');
        }

        return $this->render('user/address/form.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
            'address' => $address,
        ]);
    }

    #[Route('/edit/{id}', name: 'edit', methods: ['GET', 'PUT'])]
    public function edit (Address $address, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $form = $this->createForm(UserAddressFormType::class, $address);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($address);
            $this->em->flush();
            $this->addFlash(
                'success',
                $this->translator->trans('user.address.update.success')
            );
            return $this->redirectToRoute('address.index');
        }

        return $this->render('user/address/form.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
            'address' => $address,
        ]);
    }

    #[Route('/delete/{id}', name: 'delete', methods: ['POST'])]
    public function delete (Address $address, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete'.$address->getId(), $request->request->get('_token'))) {
            $this->em->remove($address);
            $this->em->flush();

            $this->addFlash(
                'success',
                $this->translator->trans('user.address.delete.success')
            );
        }

        return $this->redirectToRoute('address.index');
    }
}
