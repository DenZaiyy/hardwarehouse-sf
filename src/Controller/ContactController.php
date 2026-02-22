<?php

namespace App\Controller;

use App\Service\RateLimiterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    public function __construct(private readonly RateLimiterService $rateLimiter)
    {
    }

    #[Route('/contact', name: 'app.contact')]
    public function index(Request $request): Response
    {
        if (!$this->rateLimiter->checkContact()) {
            $retryAfter = $this->rateLimiter->getRetryAfter();
            $this->addFlash('danger', "Trop de tentatives. Réessayez dans $retryAfter secondes.");

            return $this->redirectToRoute('app.contact');
        }

        $form = null;

        return $this->render('contact/index.html.twig', [
            'form' => $form,
        ]);
    }
}
