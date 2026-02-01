<?php

namespace App\Controller\Api;

use App\Dto\Api\Brands\BrandDto;
use App\Dto\Api\Brands\BrandsProductsDto;
use App\Service\ApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
        $brands = $this->apiService->fetchAll('brands', BrandDto::class);

        return $this->render('brand/index.html.twig', [
            'brands' => $brands,
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug, Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $brand = $this->apiService->fetchOne('brands/'.$slug, BrandDto::class);

        $result = $this->apiService->fetchPaginated(
            "brands/$slug/products",
            BrandsProductsDto::class,
            ['page' => $page, 'limit' => 2]
        );

        return $this->render('brand/show.html.twig', [
            'brand' => $brand,
            'products' => $result['data'],
            'pagination' => $result['meta'],
        ]);
    }
}
