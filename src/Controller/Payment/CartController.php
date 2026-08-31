<?php

namespace App\Controller\Payment;

use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/cart', name: 'cart.')]
class CartController extends AbstractController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('order/cart/index.html.twig', [
            'cart' => $this->cartService->getCart(),
            'totals' => $this->cartService->computeTotals(),
            'count' => $this->cartService->getCount(),
        ]);
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(Request $request): Response
    {
        if ($invalid = $this->checkCsrfToken($request)) {
            return $invalid;
        }

        $slug = $request->request->getString('slug');
        $quantity = $request->request->getInt('quantity', 1);

        try {
            $this->cartService->addProduct($slug, $quantity);

            $this->addFlash('success', 'Produit ajouté au panier.');

            return $this->redirectToRoute('cart.index');
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());

            $referer = $request->headers->get('referer');

            return $this->redirect($referer ?: $this->generateUrl('cart.index'));
        }
    }

    #[Route('/decrease/{productId}', name: 'decrease', methods: ['POST'])]
    public function decrease(string $productId, Request $request): Response
    {
        if ($invalid = $this->checkCsrfToken($request)) {
            return $invalid;
        }

        $currentQtt = $request->request->getInt('current_qtt');

        try {
            $this->cartService->decrease($productId, $currentQtt);

            $this->addFlash('success', 'Quantité mise à jour.');

            return $this->redirectToRoute('cart.index');
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('cart.index');
        }
    }

    #[Route('/increase/{productId}', name: 'increase', methods: ['POST'])]
    public function increase(string $productId, Request $request): Response
    {
        if ($invalid = $this->checkCsrfToken($request)) {
            return $invalid;
        }

        $currentQtt = $request->request->getInt('current_qtt');
        try {
            $this->cartService->increase($productId, $currentQtt);

            $this->addFlash('success', 'Quantité mise à jour.');

            return $this->redirectToRoute('cart.index');
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('cart.index');
        }
    }

    #[Route('/remove/{productId}', name: 'remove', methods: ['POST'])]
    public function remove(string $productId, Request $request): Response
    {
        if ($invalid = $this->checkCsrfToken($request)) {
            return $invalid;
        }

        try {
            $this->cartService->removeProduct($productId);
            $this->addFlash('success', 'Produit retiré du panier.');

            return $this->redirectToRoute('cart.index');
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('cart.index');
        }
    }

    #[Route('/clear', name: 'clear', methods: ['POST'])]
    public function clear(Request $request): Response
    {
        if ($invalid = $this->checkCsrfToken($request)) {
            return $invalid;
        }

        try {
            $this->cartService->clear();
            $this->addFlash('success', 'Panier vidé.');

            return $this->redirectToRoute('cart.index');
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('cart.index');
        }
    }

    /**
     * These routes are plain POST forms, not Symfony Forms, so the framework's own
     * form CSRF protection (config/packages/csrf.yaml) never runs for them.
     */
    private function checkCsrfToken(Request $request): ?Response
    {
        $token = $request->request->getString('_csrf_token');

        if ($this->csrfTokenManager->isTokenValid(new CsrfToken('cart', $token))) {
            return null;
        }

        $this->addFlash('danger', 'Jeton de sécurité invalide, merci de réessayer.');

        return $this->redirectToRoute('cart.index');
    }
}
