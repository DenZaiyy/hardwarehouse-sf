<?php

declare(strict_types=1);

namespace App\Controller\Payment;

use App\Service\Checkout\CheckoutStateManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/checkout', name: 'checkout.')]
final class CheckoutController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('order/checkout/index.html.twig');
    }

    #[Route('/reset', name: 'reset', methods: ['POST'])]
    public function reset(CheckoutStateManager $checkoutStateManager): Response
    {
        $checkoutStateManager->reset();

        return $this->redirectToRoute('checkout.index');
    }
}
