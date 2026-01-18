<?php

namespace App\Controller;

use App\Service\ApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/categories', name: 'category.')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly ApiService $apiService,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $categories = $this->apiService->getData('categories', null);
        return $this->render('category/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $category = $this->apiService->getData('categories', $slug);
        $products = $category['Products'] ?? null;

        //dd($category, $products);

        return $this->render('category/show.html.twig', [
            'category' => $category,
            'products' => $products,
        ]);
    }
}
