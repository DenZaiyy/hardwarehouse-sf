<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Tests\Http\ApiMockResponseFactory;
use PHPUnit\Framework\Attributes\After;

/**
 * Décrit, test par test, ce que répond l'API du catalogue simulée (voir ApiMockResponseFactory).
 */
trait FakesCatalogApi
{
    /** Slug aléatoire : les lignes de panier d'un test ne se mélangent pas à celles d'un autre. */
    private static function newProductSlug(): string
    {
        return 'ryzen-7-7800x3d-'.bin2hex(random_bytes(4));
    }

    /**
     * L'API renvoie cette fiche pour le slug donné. L'identifiant, dérivé du slug, est celui
     * que reprend la ligne de panier : une fiche enregistrée deux fois garde le même produit.
     *
     * @param list<array{name: string, type: string, value: string}> $attributes caractéristiques techniques
     */
    private function apiHasProduct(string $slug, bool $active = true, int $stock = 3, array $attributes = []): string
    {
        $id = substr(hash('sha256', $slug), 0, 24);

        ApiMockResponseFactory::respondWith('products/'.$slug, [
            'id' => $id,
            'name' => 'AMD Ryzen 7 7800X3D',
            'slug' => $slug,
            'price' => 449.9,
            'active' => $active,
            'thumbnail' => 'https://example.com/ryzen-7-7800x3d.webp',
            'images' => [],
            'shortDescription' => 'Processeur 8 cœurs pour le jeu',
            'description' => 'Fiche produit de test',
            'category' => ['id' => 'cat-processeurs', 'name' => 'Processeurs', 'slug' => 'processeurs', 'active' => true, 'productsCount' => 1],
            'brand' => ['id' => 'brand-amd', 'name' => 'AMD', 'slug' => 'amd', 'active' => true, 'productsCount' => 1],
            'stock' => ['quantity' => $stock],
            'productAttributeValues' => array_map(static fn (int $index, array $attribute): array => [
                'id' => 'valeur-'.$index,
                'value' => $attribute['value'],
                'categoryAttribute' => [
                    'id' => 'attribut-categorie-'.$index,
                    'displayOrder' => $index,
                    'attribute' => ['id' => 'attribut-'.$index, 'name' => $attribute['name'], 'type' => $attribute['type']],
                ],
            ], array_keys($attributes), $attributes),
        ]);

        return $id;
    }

    private function apiFailsOn(string $path, int $status = 503): void
    {
        ApiMockResponseFactory::respondWith($path, ['error' => 'Service Unavailable'], $status);
    }

    #[After]
    protected function forgetApiResponses(): void
    {
        ApiMockResponseFactory::reset();
    }
}
