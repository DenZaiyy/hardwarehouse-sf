<?php

namespace App\Controller\Api;

use App\Dto\Api\Attributes\ProductAttributeValueDto;
use App\Dto\Api\Products\ProductDto;
use App\Service\ApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/products', name: 'product.')]
final class ProductController extends AbstractController
{
    public function __construct(
        private readonly ApiService $apiService,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $products = $this->apiService->fetchAll('products', ProductDto::class);

        return $this->render('product/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $product = $this->apiService->fetchOne('products/' . $slug, ProductDto::class);

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'attributes' => $product->productAttributeValues,
        ]);
    }
}
