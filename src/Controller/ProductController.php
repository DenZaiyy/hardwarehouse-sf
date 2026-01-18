<?php

namespace App\Controller;

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
        $products = $this->apiService->getData('products', null);
        //dd($products);

        return $this->render('product/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $product = $this->apiService->getData('products', $slug);
        $attributes = $product['productAttributeValues'];
        //dd($product, $attributes);


        return $this->render('product/show.html.twig', [
            'product' => $product,
            'attributes' => $attributes,
        ]);
    }
}
