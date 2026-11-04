<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\DTO\Configurator\ConfiguratorState;
use App\Enum\ComponentSlot;
use App\Tests\Http\ApiMockResponseFactory;
use PHPUnit\Framework\Attributes\Before;

/**
 * Catalogue simulé du configurateur : chaque composant ajouté figure dans la lecture groupée, dans la liste de sa
 * catégorie et en fiche (que relit CartService).
 */
trait FakesConfiguratorCatalog
{
    use FakesCatalogApi;

    /** Processeur AM5 qui accepte la DDR5, sans partie graphique, livré avec son ventirad */
    private const array AM5_CPU = [
        'socket' => ['value' => 'AM5'],
        'ddr_supported' => ['value' => 'DDR5', 'values' => ['DDR5']],
        'tdp' => ['value' => '120', 'numericValue' => 120],
        'integrated_graphics' => ['value' => 'false'],
        'cooler_included' => ['value' => 'true'],
    ];

    /** @var list<array<string, mixed>> fiches que renvoie la lecture groupée simulée */
    private array $specs = [];

    /** @var array<string, list<array<string, mixed>>> produits que liste chaque catégorie simulée */
    private array $lists = [];

    /** Catégories vides tant qu'un test n'y ajoute pas de produit. */
    #[Before]
    protected function emptyConfiguratorCategories(): void
    {
        foreach (ComponentSlot::cases() as $slot) {
            foreach ($slot->categorySlugs() as $category) {
                ApiMockResponseFactory::respondWith("categories/$category/products", [
                    'data' => [],
                    'total' => 0,
                    'meta' => ['page' => 1, 'limit' => 25, 'totalPages' => 0, 'hasNext' => false, 'hasPrev' => false],
                ]);
            }
        }
    }

    /**
     * Ajoute un composant au catalogue simulé. Son nom (Produit suivi de son identifiant) permet de le retrouver dans la page.
     *
     * @param array<string, array{value: string, values?: list<string>, numericValue?: float|int}> $attributes
     */
    private function apiHasComponent(string $category, array $attributes = []): string
    {
        $slug = $category.'-'.bin2hex(random_bytes(4));
        $id = $this->apiHasProduct($slug, stock: 5, price: 374.92);
        $name = 'Produit '.$id;

        $this->specs[] = [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'price' => 374.92,
            'active' => true,
            'thumbnail' => '/images/products/'.$slug.'/thumbnail.webp',
            'category' => ['slug' => $category],
            'stock' => ['quantity' => 5],
            'attributes' => $attributes,
        ];
        ApiMockResponseFactory::respondWith('products', ['data' => $this->specs, 'total' => \count($this->specs)]);

        $this->lists[$category][] = ['name' => $name] + self::productPayload($slug, stock: 5, price: 374.92);
        ApiMockResponseFactory::respondWith("categories/$category/products", [
            'data' => $this->lists[$category],
            'total' => \count($this->lists[$category]),
            'meta' => ['page' => 1, 'limit' => 25, 'totalPages' => 1, 'hasNext' => false, 'hasPrev' => false],
        ]);

        return $id;
    }

    /**
     * PC complet et compatible, sans refroidissement puisque le processeur fournit le sien : (120 + 220 + 100) × 1,3
     * = 572 W, couverts par l'alimentation de 750 W.
     */
    private function completeBuild(): ConfiguratorState
    {
        $state = new ConfiguratorState();
        $state->choose(ComponentSlot::CPU, $this->apiHasComponent('processeurs', self::AM5_CPU));
        $state->choose(ComponentSlot::MOTHERBOARD, $this->apiHasComponent('cartes-meres', ['socket' => ['value' => 'AM5'], 'ddr_type' => ['value' => 'DDR5'], 'form_factor' => ['value' => 'ATX'], 'memory_slots' => ['value' => '4', 'numericValue' => 4]]));
        $state->choose(ComponentSlot::RAM, $this->apiHasComponent('memoire-ram', ['ddr_type' => ['value' => 'DDR5']]));
        $state->choose(ComponentSlot::GPU, $this->apiHasComponent('cartes-graphiques', ['power_draw' => ['value' => '220', 'numericValue' => 220]]));
        $state->choose(ComponentSlot::STORAGE, $this->apiHasComponent('ssd'));
        $state->choose(ComponentSlot::CASE, $this->apiHasComponent('boitiers', ['form_factors_supported' => ['value' => 'ATX', 'values' => ['ATX']]]));
        $state->choose(ComponentSlot::PSU, $this->apiHasComponent('alimentations', ['psu_power' => ['value' => '750', 'numericValue' => 750]]));

        return $state;
    }
}
