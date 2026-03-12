<?php

declare(strict_types=1);

namespace App\Controller\User\Profile;

use App\Entity\Address;
use App\Entity\User;
use App\Enum\AddressType;
use App\Form\User\UserAddressFormType;
use App\Repository\AddressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Turbo\TurboBundle;

#[Route('/profile/address', name: 'address.')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class AddressController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(AddressRepository $repository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $deliveryAddresses = $repository->findBy(['user_info' => $user, 'type' => AddressType::DELIVERY]);
        $billingAddresses = $repository->findBy(['user_info' => $user, 'type' => AddressType::BILLING]);

        return $this->render('user/address/index.html.twig', [
            'user' => $user,
            'billingAddresses' => $billingAddresses,
            'deliveryAddresses' => $deliveryAddresses,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $address = new Address();

        return $this->handleForm($request, $address, $user);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Address $address, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->handleForm($request, $address, $user);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Address $address, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete'.$address->getId(), (string) $request->request->get('_token'))) {
            $this->em->remove($address);
            $this->em->flush();

            $this->addFlash(
                'success',
                $this->translator->trans('user.address.delete.success')
            );
        }

        return $this->redirectToRoute('address.index');
    }

    private function handleForm(Request $request, Address $address, User $user): Response
    {
        $form = $this->createForm(UserAddressFormType::class, $address);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $default = (bool) $form->get('isDefault')->getData();
            /** @var AddressType $type */
            $type = $form->get('type')->getData();

            if ($default) {
                $alreadyDefaultSameType = $this->em->getRepository(Address::class)->findOneBy([
                    'user_info' => $user,
                    'is_default' => true,
                    'type' => $type,
                ]);

                if ($alreadyDefaultSameType) {
                    $alreadyDefaultSameType->setIsDefault(false);
                    $this->em->persist($alreadyDefaultSameType);

                    $this->addFlash('info', $this->translator->trans('user.address.default.replaced'));
                }

                $address->setIsDefault(true);
            }

            $address->setUserInfo($user);

            $this->em->persist($address);
            $this->em->flush();

            $this->addFlash('success', $this->translator->trans('user.address.save.success'));

            // Si c'est une requête AJAX/Turbo, renvoyer des streams
            if ($request->isXmlHttpRequest() || $request->headers->has('Turbo-Frame') || TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
                $deliveryAddresses = $this->em->getRepository(Address::class)->findBy(['user_info' => $user, 'type' => AddressType::DELIVERY]);
                $billingAddresses = $this->em->getRepository(Address::class)->findBy(['user_info' => $user, 'type' => AddressType::BILLING]);

                $response = new Response();
                $response->headers->set('Content-Type', 'text/vnd.turbo-stream.html');

                return $this->render('user/address/success.stream.html.twig', [
                    'address' => $address,
                    'deliveryAddresses' => $deliveryAddresses,
                    'billingAddresses' => $billingAddresses,
                ], $response);
            }

            return $this->redirectToRoute('address.index');
        }

        // Si requête depuis un turbo frame ET il y a des erreurs, rester dans le frame
        if ($request->headers->has('Turbo-Frame') && $form->isSubmitted() && !$form->isValid()) {
            return $this->render('user/address/_form.html.twig', [
                'form' => $form,
                'address' => $address,
            ]);
        }

        return $this->render('user/address/_form.html.twig', [
            'form' => $form,
            'address' => $address,
        ]);
    }
}
