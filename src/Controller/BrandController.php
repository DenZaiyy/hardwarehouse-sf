<?php

namespace App\Controller;

use App\Service\ApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/brands', name: 'brand.')]
final class BrandController extends AbstractController
{
    public function __construct(
        private readonly ApiService $apiService,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $brands = $this->apiService->getData('brands', null);
        //dd($brands);

        return $this->render('brand/index.html.twig', [
            'brands' => $brands,
        ]);
    }
}
