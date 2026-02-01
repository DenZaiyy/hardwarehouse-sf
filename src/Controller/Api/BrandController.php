<?php

namespace App\Controller\Api;

use App\Dto\Api\Brands\BrandDto;
use App\Dto\Api\Brands\BrandWithProductsDto;
use App\Service\ApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

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
        $brands = $this->apiService->fetchAll('brands', BrandDto::class);

        return $this->render('brand/index.html.twig', [
            'brands' => $brands,
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        try {
            $brand = $this->apiService->fetchOne('brands/' . $slug, BrandWithProductsDto::class);
        } catch (HttpExceptionInterface) {
            throw $this->createNotFoundException('Marque non trouvée');
        }

        return $this->render('brand/show.html.twig', [
            'brand' => $brand,
            'products' => $brand->products ?? null,
        ]);
    }
}
