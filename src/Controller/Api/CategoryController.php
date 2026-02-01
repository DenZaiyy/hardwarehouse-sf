<?php

namespace App\Controller\Api;

use App\Dto\Api\Categories\CategoryDto;
use App\Dto\Api\Categories\CategoryProductsDto;
use App\Service\ApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        // $categories = $this->apiService->fetchAll('categories', CategoryDto::class);
        $result = $this->apiService->fetchPaginated(
            'categories',
            CategoryDto::class,
            ['page' => $page, 'limit' => 2]
        );

        return $this->render('category/index.html.twig', [
            'categories' => $result['data'],
            'pagination' => $result['meta'],
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug, Request $request): Response
    {
        $page = $request->query->getInt('page', 1);

        $category = $this->apiService->fetchOne('categories/'.$slug, CategoryDto::class);
        $result = $this->apiService->fetchPaginated(
            "categories/$slug/products",
            CategoryProductsDto::class,
            ['page' => $page, 'limit' => 2]
        );

        return $this->render('category/show.html.twig', [
            'category' => $category,
            'products' => $result['data'],
            'pagination' => $result['meta'],
        ]);
    }
}
