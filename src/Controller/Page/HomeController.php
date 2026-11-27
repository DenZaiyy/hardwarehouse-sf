<?php

declare(strict_types=1);

namespace App\Controller\Page;

use App\DTO\Api\Brands\BrandDto;
use App\DTO\Api\Categories\CategoryDto;
use App\Repository\RatingRepository;
use App\Service\ApiService;
use App\Service\Catalog\CategoryCatalog;
use App\Service\Catalog\PromotionSelector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Accueil : les textes sont dans les traductions (home.*), le contrôleur ne fournit que des données réelles, à savoir
 * les chiffres du catalogue, la remise en cours et les catégories mises en avant.
 */
class HomeController extends AbstractController
{
    #[Route('/', name: 'homepage', options: ['sitemap' => true])]
    public function index(
        ApiService $apiService,
        CategoryCatalog $categoryCatalog,
        RatingRepository $ratingRepository,
        PromotionSelector $promotionSelector,
    ): Response {
        $brands = $apiService->fetchAllCached('brands', BrandDto::class);
        $categories = $categoryCatalog->all();

        return $this->render('home/index.html.twig', [
            'stats' => $this->stats($categories, \count($brands), $ratingRepository->globalSummary()),
            'promotion' => $promotionSelector->featured(),
            'brands' => $brands,
            'featured_categories' => \array_slice($categories, 0, 4),
        ]);
    }

    /**
     * Chiffres du bandeau, tous réels : références et marques du catalogue, puis la note moyenne des avis vérifiés,
     * ou le nombre de catégories tant qu'aucun avis n'est publié.
     *
     * @param list<CategoryDto>                      $categories
     * @param array{average: float|null, count: int} $reviews
     *
     * @return list<array{value: int|float, decimals: int, suffix: string, label: string, count: int}>
     */
    private function stats(array $categories, int $brands, array $reviews): array
    {
        $products = array_sum(array_map(static fn (CategoryDto $category): int => $category->productsCount ?? 0, $categories));

        return [
            ['value' => $products, 'decimals' => 0, 'suffix' => '', 'label' => 'home.stats.products', 'count' => $products],
            ['value' => $brands, 'decimals' => 0, 'suffix' => '', 'label' => 'home.stats.brands', 'count' => $brands],
            null !== $reviews['average']
                ? ['value' => $reviews['average'], 'decimals' => 1, 'suffix' => '/5', 'label' => 'home.stats.rating', 'count' => $reviews['count']]
                : ['value' => \count($categories), 'decimals' => 0, 'suffix' => '', 'label' => 'home.stats.categories', 'count' => \count($categories)],
        ];
    }
}
