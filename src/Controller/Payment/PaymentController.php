<?php

namespace App\Controller\Payment;

use App\Entity\Order;
use App\Service\CartService;
use App\Service\Checkout\CheckoutStateManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/payment', name: 'payment.')]
class PaymentController extends AbstractController
{
    #[Route('/success/{reference}', name: 'success')]
    public function success(
        string $reference,
        CartService $cartService,
        CheckoutStateManager $checkoutStateManager,
        EntityManagerInterface $entityManager,
    ): Response {
        // Find the order by reference
        $order = $entityManager->getRepository(Order::class)->findOneBy(['reference' => $reference]);

        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

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
