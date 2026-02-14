<?php

namespace App\Controller\Api;

use App\Dto\Api\Products\ProductDto;
use App\Service\ApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: ['en' => '/products', 'fr' => '/produits'], name: 'product.')]
final class ProductController extends AbstractController
{
    public function __construct(
        private readonly ApiService $apiService,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);

        $result = $this->apiService->fetchPaginated(
            'products',
            ProductDto::class,
            ['page' => $page, 'limit' => 12]
        );

        return $this->render('product/index.html.twig', [
            'products' => $result['data'],
            'pagination' => $result['meta'],
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $product = $this->apiService->fetchOne('products/'.$slug, ProductDto::class);

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'attributes' => $product->productAttributeValues,
        ]);
    }
}
