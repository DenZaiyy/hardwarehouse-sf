<?php

declare(strict_types=1);

namespace App\Controller\User\Profile;

use App\Entity\Order;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Security\Voter\OrderVoter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile/orders', name: 'order.')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class OrderController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(OrderRepository $orders): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('user/order/index.html.twig', [
            'orders' => $orders->findForCustomer($user),
        ]);
    }

    /** La commande d'un autre client est introuvable : son existence n'est pas révélée. */
    #[Route('/{reference}', name: 'show', methods: ['GET'])]
    #[IsGranted(OrderVoter::VIEW, subject: 'order', statusCode: Response::HTTP_NOT_FOUND)]
    public function show(#[MapEntity(mapping: ['reference' => 'reference'])] Order $order): Response
    {
        return $this->render('user/order/show.html.twig', [
            'order' => $order,
        ]);
    }
}
