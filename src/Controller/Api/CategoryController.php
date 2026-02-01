<?php

namespace App\Controller\Api;

use App\Dto\Api\Categories\CategoryDto;
use App\Dto\Api\Categories\CategoryWithProductsDto;
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
        $categories = $this->apiService->fetchAll('categories', CategoryDto::class);

        return $this->render('category/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $category = $this->apiService->fetchOne('categories/' . $slug, CategoryWithProductsDto::class);

        return $this->render('category/show.html.twig', [
            'category' => $category,
            'products' => $category->products ?? null,
        ]);
    }
}
