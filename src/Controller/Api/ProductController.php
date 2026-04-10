<?php

namespace App\Controller\Api;

use App\DTO\Api\Brands\BrandDto;
use App\DTO\Api\Categories\CategoryDto;
use App\DTO\Api\Products\ProductDto;
use App\SEO\Schema\ProductSchemaBuilder;
use App\Service\ApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $limit = $request->query->getInt('limit', 20);
        $categories = $request->query->get('categories', '');
        $brands = $request->query->get('brands', '');
        $minPrice = $request->query->get('minPrice');
        $maxPrice = $request->query->get('maxPrice');

        $categories = '' !== $categories ? array_filter(explode(',', $categories)) : [];
        $brands = '' !== $brands ? array_filter(explode(',', $brands)) : [];

        $params = [
            'page' => $page,
            'limit' => $limit,
        ];

        if (!empty($categories)) {
            $params['categories'] = implode(',', $categories);
        }

        if (!empty($brands)) {
            $params['brands'] = implode(',', $brands);
        }

        if (null !== $minPrice && '' !== $minPrice) {
            $params['minPrice'] = $minPrice;
        }

        if (null !== $maxPrice && '' !== $maxPrice) {
            $params['maxPrice'] = $maxPrice;
        }

        $categoriesData = $this->apiService->fetchAll('categories', CategoryDto::class);
        $brandsData = $this->apiService->fetchAll('brands', BrandDto::class);

        try {
            $result = $this->apiService->fetchPaginated(
                'products',
                ProductDto::class,
                $params,
            );

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
                    'total' => $result['total'] ?? 0,
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

        return $this->render('product/index.html.twig', [
            'products' => $result['data'],
            'pagination' => $result['meta'],
            'categories' => $categoriesData,
            'brands' => $brandsData,
            'limit' => $limit,
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug, ProductSchemaBuilder $productSchemaBuilder): Response
    {
        $product = $this->apiService->fetchOne('products/'.$slug, ProductDto::class);
        $productSchema = $productSchemaBuilder->build($product);

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'attributes' => $product->productAttributeValues,
            'productSchema' => $productSchema,
        ]);
    }
}
