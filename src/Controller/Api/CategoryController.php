<?php

namespace App\Controller\Api;

use App\DTO\Api\Brands\BrandDto;
use App\DTO\Api\Categories\CategoryDto;
use App\DTO\Api\Categories\CategoryProductsDto;
use App\Service\ApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    public function index(): Response
    {
        $categories = [];

        try {
            $categories = $this->apiService->fetchAll('categories', CategoryDto::class);
        } catch (\Error) {
        }

        return $this->render('category/index.html.twig', [
            'categoriesCount' => count($categories),
            'categories' => $categories,
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug, Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 12);
        $brands = $request->query->get('brands', '');
        $minPrice = $request->query->get('minPrice');
        $maxPrice = $request->query->get('maxPrice');

        $brands = '' !== $brands ? array_filter(explode(',', $brands)) : [];

        $params = [
            'page' => $page,
            'limit' => $limit,
        ];

        if (!empty($brands)) {
            $params['brands'] = implode(',', $brands);
        }

        if (null !== $minPrice && '' !== $minPrice) {
            $params['minPrice'] = $minPrice;
        }

        if (null !== $maxPrice && '' !== $maxPrice) {
            $params['maxPrice'] = $maxPrice;
        }

        $category = $this->apiService->fetchOne('categories/'.$slug, CategoryDto::class);
        try {
            $result = $this->apiService->fetchPaginated(
                "categories/$slug/products",
                CategoryProductsDto::class,
                $params
            );

            /** @var CategoryProductsDto[] $data */
            $data = $result['data'];
            /** @var BrandDto[] $brands */
            $brands = [];
            $seen = [];

            foreach ($data as $product) {
                $brand = $product->getBrand();
                if (null === $brand) {
                    continue;
                }
                $brandSlug = $brand->getSlug();
                if (!isset($seen[$brandSlug])) {
                    $seen[$brandSlug] = true;
                    $brands[] = $brand;
                }
            }

            if ($request->isXmlHttpRequest()) {
                $productsHtml = $this->renderView('_partials/_products_grid.html.twig', [
                    'products' => $result['data'],
                ]);

                $paginationHtml = $this->renderView('_partials/_pagination_ajax.html.twig', [
                    'pagination' => $result['meta'],
                ]);

                return new JsonResponse([
                    'success' => true,
                    'productsHtml' => $productsHtml,
                    'paginationHtml' => $paginationHtml,
                    'total' => $result['total'],
                ]);
            }
        } catch (\Throwable $e) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Failed to fetch products',
                ], 500);
            }

            throw $e;
        }

        return $this->render('category/show.html.twig', [
            'category' => $category,
            'products' => $result['data'],
            'brands' => $brands,
            'pagination' => $result['meta'],
            'limit' => $limit,
        ]);
    }
}
