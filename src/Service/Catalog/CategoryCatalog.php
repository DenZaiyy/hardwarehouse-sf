<?php

declare(strict_types=1);

namespace App\Service\Catalog;

use App\DTO\Api\Categories\CategoryDto;
use App\Exception\Api\ApiException;
use App\Service\ApiService;

/**
 * Catégories du catalogue dans l'ordre d'affichage de la boutique : menu, filtres, page des catégories et accueil.
 * L'API les renvoie de la plus récente à la plus ancienne. La boutique, qui porte déjà leurs libellés par slug
 * (traductions category.<slug>), fixe aussi leur ordre : celui de la maquette, du montage d'un PC aux périphériques.
 * Une catégorie qu'elle ne connaît pas encore vient à la fin, par ordre alphabétique.
 */
final readonly class CategoryCatalog
{
    private const array ORDER = [
        'processeurs',
        'cartes-meres',
        'memoire-ram',
        'cartes-graphiques',
        'ssd',
        'disques-durs',
        'refroidissement',
        'watercooling',
        'boitiers',
        'alimentations',
        'ecrans',
        'peripheriques',
    ];

    public function __construct(private ApiService $apiService)
    {
    }

    /**
     * @return list<CategoryDto>
     *
     * @throws ApiException quand l'API du catalogue ne répond pas
     */
    public function all(): array
    {
        $categories = array_values($this->apiService->fetchAllCached('categories', CategoryDto::class));
        $rank = array_flip(self::ORDER);
        $unknown = \count(self::ORDER);
        $collator = new \Collator('fr_FR');

        usort($categories, static fn (CategoryDto $a, CategoryDto $b): int => (($rank[$a->slug] ?? $unknown) <=> ($rank[$b->slug] ?? $unknown))
            ?: (int) $collator->compare($a->name, $b->name));

        return $categories;
    }
}
