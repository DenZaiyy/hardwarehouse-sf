<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Repository\RatingRepository;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Notes des cartes produit : la grille les lit toutes en une requête (rating_summaries), jamais carte par carte.
 */
final readonly class RatingExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(private RatingRepository $ratingRepository)
    {
    }

    /**
     * @param iterable<object> $products produits de l'API, avec leur identifiant (propriété id)
     *
     * @return array<string, array{average: float, count: int}>
     */
    public function summaries(iterable $products): array
    {
        $ids = [];
        foreach ($products as $product) {
            if (property_exists($product, 'id') && \is_string($product->id)) {
                $ids[] = $product->id;
            }
        }

        return $this->ratingRepository->summaries($ids);
    }
}
