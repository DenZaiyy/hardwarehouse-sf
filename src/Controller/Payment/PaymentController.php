<?php

namespace App\Controller\Payment;

use App\Entity\Order;
use App\Security\Voter\OrderVoter;
use App\Service\CartService;
use App\Service\Checkout\CheckoutStateManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/payment', name: 'payment.')]
class PaymentController extends AbstractController
{
    #[Route('/success/{reference}', name: 'success')]
    #[IsGranted(OrderVoter::VIEW_CONFIRMATION, subject: 'order', statusCode: Response::HTTP_NOT_FOUND)]
    public function success(
        #[MapEntity(mapping: ['reference' => 'reference'])]
        Order $order,
        CartService $cartService,
        CheckoutStateManager $checkoutStateManager,
    ): Response {
        // Clear cart and checkout state after payment success
        $cartService->clear();
        $checkoutStateManager->reset();

        return $this->render('order/payment/success.html.twig', [
            'message' => 'Votre commande a été créée avec succès.',
            'order' => $order,
        ]);
    }

    #[Route('/cancel', name: 'cancel')]
    public function cancel(): Response
    {
        return $this->render('order/payment/cancel.html.twig', [
            'message' => 'Le paiement a été annulé. Vous pouvez reprendre votre commande.',
        ]);
    }
}
