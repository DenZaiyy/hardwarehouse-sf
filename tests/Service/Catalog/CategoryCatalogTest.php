<?php

declare(strict_types=1);

namespace App\Tests\Service\Catalog;

use App\DTO\Api\Categories\CategoryDto;
use App\Service\Catalog\CategoryCatalog;
use App\Tests\Support\FakesCatalogApi;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CategoryCatalogTest extends KernelTestCase
{
    use FakesCatalogApi;

    public function testCategoriesFollowTheMockupOrderWhateverTheApiOrder(): void
    {
        self::bootKernel();
        // L'API renvoie la catégorie la plus récente en premier : l'ordre inverse du menu
        $this->apiListsCategories([
            ...array_map(static fn (string $slug): array => ['slug' => $slug], ['peripheriques', 'ecrans', 'alimentations', 'boitiers',
                'watercooling', 'refroidissement', 'disques-durs', 'ssd', 'cartes-graphiques', 'memoire-ram', 'cartes-meres', 'processeurs']),
            ['slug' => 'reseau', 'name' => 'Réseau'],
            ['slug' => 'audio', 'name' => 'Audio'],
        ]);

        $catalog = static::getContainer()->get(CategoryCatalog::class);
        self::assertInstanceOf(CategoryCatalog::class, $catalog);
        $categories = $catalog->all();

        // Ordre de la maquette, puis les catégories qu'il ne connaît pas, par ordre alphabétique
        self::assertSame(
            ['processeurs', 'cartes-meres', 'memoire-ram', 'cartes-graphiques', 'ssd', 'disques-durs', 'refroidissement',
                'watercooling', 'boitiers', 'alimentations', 'ecrans', 'peripheriques', 'audio', 'reseau'],
            array_map(static fn (CategoryDto $category): string => $category->slug, $categories),
        );
    }
}
