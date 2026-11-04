<?php

declare(strict_types=1);

namespace App\Service\Configurator;

use App\DTO\Api\Categories\CategoryProductsDto;
use App\DTO\Api\PaginationMeta;
use App\DTO\Api\Products\ProductSpecDto;
use App\Enum\ComponentSlot;
use App\Exception\Api\ApiException;
use App\Service\ApiService;
use Psr\Log\LoggerInterface;

/**
 * Seul point d'accès du configurateur à l'API catalogue : la lecture groupée des composants choisis, puis la liste des
 * produits compatibles d'une catégorie. Une étape coûte ainsi deux appels, quelle que soit la taille de la sélection.
 * Aucun des deux n'est mis en cache : prix, stock et compatibilité doivent être frais.
 */
final readonly class ConfiguratorCatalogManager
{
    /** Identifiants admis par la lecture groupée de l'API */
    private const int MAX_IDS_PER_READ = 20;

    public function __construct(
        private ApiService $apiService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Composants choisis, actifs ou non, indexés par identifiant. Un produit absent de la réponse a été supprimé du
     * catalogue, un produit inactif retiré de la vente : dans les deux cas, il est à signaler comme indisponible.
     *
     * @param list<string> $ids
     *
     * @return array<string, ProductSpecDto>
     */
    public function specs(array $ids): array
    {
        $products = [];
        foreach (array_chunk($ids, self::MAX_IDS_PER_READ) as $chunk) {
            foreach ($this->apiService->fetchProductSpecs($chunk) as $product) {
                $products[$product->id] = $product;
            }
        }

        return $products;
    }

    /**
     * Produits actifs d'une catégorie de l'emplacement qui respectent les filtres, paginés par l'API (25 par page).
     * Sans filtres possibles (null : aucun produit ne peut convenir), aucune requête n'est faite.
     *
     * @param array<string, string>|null $filters paramètres renvoyés par ConfiguratorCompatibilityManager::filtersFor()
     *
     * @return array{data: array<CategoryProductsDto>, total: int, meta: PaginationMeta}
     */
    public function compatibleProducts(ComponentSlot $slot, string $categorySlug, ?array $filters, int $page = 1): array
    {
        if (!\in_array($categorySlug, $slot->categorySlugs(), true)) {
            throw new \InvalidArgumentException(\sprintf('La catégorie "%s" ne fournit pas l\'emplacement "%s".', $categorySlug, $slot->value));
        }

        if (null === $filters) {
            return ['data' => [], 'total' => 0, 'meta' => new PaginationMeta(total: 0, page: 1, limit: 25, totalPages: 0, hasNext: false, hasPrev: false)];
        }

        try {
            return $this->apiService->fetchPaginated(
                "categories/$categorySlug/products",
                CategoryProductsDto::class,
                [...$filters, 'page' => $page],
            );
        } catch (ApiException $exception) {
            if (400 === $exception->getCode()) {
                // Code d'attribut refusé : les règles de la boutique et le catalogue ne parlent plus des mêmes attributs
                $this->logger->error('Configurator filters refused by the catalogue API', [
                    'category' => $categorySlug,
                    'filters' => $filters,
                ]);
            }

            throw $exception;
        }
    }
}
