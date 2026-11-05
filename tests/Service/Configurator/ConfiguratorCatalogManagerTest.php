<?php

declare(strict_types=1);

namespace App\Tests\Service\Configurator;

use App\Enum\ComponentSlot;
use App\Exception\Api\ApiException;
use App\Service\ApiService;
use App\Service\Configurator\ConfiguratorCatalogManager;
use App\Tests\Http\ApiMockResponseFactory;
use App\Tests\Support\FakesCatalogApi;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\ErrorHandler\BufferingLogger;

/**
 * Le configurateur ne parle à l'API que par deux appels : la lecture groupée des composants choisis, et la liste des
 * produits compatibles d'une catégorie. Une liste n'est jamais renvoyée sans ses filtres.
 */
final class ConfiguratorCatalogManagerTest extends KernelTestCase
{
    use FakesCatalogApi;

    private BufferingLogger $logger;

    protected function setUp(): void
    {
        // Les tests qui comptent les requêtes ne doivent pas voir celles d'un test précédent
        ApiMockResponseFactory::reset();
        $this->logger = new BufferingLogger();
    }

    public function testChosenComponentsAreReadInOneCallWithdrawnOnesIncluded(): void
    {
        ApiMockResponseFactory::respondWith('products', ['data' => [
            self::spec('665f1c2a9b1e4a0012345678', 'ryzen-7-7800x3d', active: true, attributes: ['socket' => ['value' => 'AM5'], 'tdp' => ['value' => '120', 'numericValue' => 120]]),
            self::spec('665f1c2a9b1e4a0087654321', 'ryzen-5-5600x', active: false, attributes: ['socket' => ['value' => 'AM4']]),
        ], 'total' => 2]);

        $specs = $this->catalog()->specs(['665f1c2a9b1e4a0012345678', '665f1c2a9b1e4a0087654321']);

        self::assertSame(['665f1c2a9b1e4a0012345678', '665f1c2a9b1e4a0087654321'], array_keys($specs));
        self::assertSame('AM5', $specs['665f1c2a9b1e4a0012345678']->value('socket'));
        self::assertSame(120.0, $specs['665f1c2a9b1e4a0012345678']->number('tdp'));
        self::assertSame('processeurs', $specs['665f1c2a9b1e4a0012345678']->categorySlug());
        self::assertFalse($specs['665f1c2a9b1e4a0087654321']->active);

        $requests = ApiMockResponseFactory::requests();
        self::assertCount(1, $requests);
        parse_str($requests[0]['query'], $query);
        self::assertSame(['ids' => '665f1c2a9b1e4a0012345678,665f1c2a9b1e4a0087654321', 'include' => 'attributes'], $query);
    }

    public function testEmptySelectionCallsNothing(): void
    {
        self::assertSame([], $this->catalog()->specs([]));
        self::assertSame([], ApiMockResponseFactory::requests());
    }

    public function testCompatibleProductsAreActiveProductsMatchingTheFilters(): void
    {
        ApiMockResponseFactory::respondWith('categories/cartes-meres/products', [
            'data' => [self::listItem('665f1c2a9b1e4a00aaaaaaaa', 'msi-mag-b650-tomahawk')],
            'total' => 26,
            'meta' => ['page' => 2, 'limit' => 25, 'totalPages' => 2, 'hasNext' => false, 'hasPrev' => true],
        ]);

        $result = $this->catalog()->compatibleProducts(ComponentSlot::MOTHERBOARD, 'cartes-meres', ['attr[socket]' => 'AM5'], page: 2);

        self::assertSame('msi-mag-b650-tomahawk', $result['data'][0]->slug);
        self::assertSame(26, $result['total']);
        self::assertSame(2, $result['meta']->page);
        parse_str(ApiMockResponseFactory::requests()[0]['query'], $query);
        self::assertSame(['active' => 'true', 'attr' => ['socket' => 'AM5'], 'page' => '2'], $query);
    }

    public function testNoPossibleProductMeansNoCallAndAnEmptyList(): void
    {
        $result = $this->catalog()->compatibleProducts(ComponentSlot::MOTHERBOARD, 'cartes-meres', null);

        self::assertSame([], $result['data']);
        self::assertSame(0, $result['total']);
        self::assertSame([], ApiMockResponseFactory::requests());
    }

    public function testAttributeFilterRefusedByTheApiIsLoggedAsAnErrorAndNeverListedUnfiltered(): void
    {
        ApiMockResponseFactory::respondWith('categories/cartes-meres/products', ['error' => 'Attribut inconnu : socket', 'code' => 'VALIDATION_ERROR'], 400);

        try {
            $this->catalog()->compatibleProducts(ComponentSlot::MOTHERBOARD, 'cartes-meres', ['attr[socket]' => 'AM5']);
            self::fail('Une liste a été renvoyée malgré le refus des filtres.');
        } catch (ApiException $exception) {
            self::assertSame(400, $exception->getCode());
        }

        self::assertSame([LogLevel::ERROR], array_column($this->logger->cleanLogs(), 0));
        self::assertCount(1, ApiMockResponseFactory::requests());
    }

    public function testCategoryOutsideTheSlotIsRefusedWithoutCallingTheApi(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        try {
            $this->catalog()->compatibleProducts(ComponentSlot::MOTHERBOARD, 'ecrans', ['attr[socket]' => 'AM5']);
        } finally {
            self::assertSame([], ApiMockResponseFactory::requests());
        }
    }

    private function catalog(): ConfiguratorCatalogManager
    {
        $apiService = static::getContainer()->get(ApiService::class);
        self::assertInstanceOf(ApiService::class, $apiService);

        return new ConfiguratorCatalogManager($apiService, $this->logger);
    }

    /**
     * Produit tel que le renvoie la lecture groupée de l'API (GET /products?ids=…&include=attributes).
     *
     * @param array<string, array{value: string, values?: list<string>, numericValue?: float|int}> $attributes
     *
     * @return array<string, mixed>
     */
    private static function spec(string $id, string $slug, bool $active, array $attributes): array
    {
        return [
            'id' => $id,
            'name' => 'Produit '.$slug,
            'slug' => $slug,
            'price' => 374.92,
            'discountPrice' => null,
            'discountAmount' => null,
            'active' => $active,
            'thumbnail' => '/images/products/'.$slug.'/thumbnail.webp',
            'category' => ['slug' => 'processeurs'],
            'stock' => ['quantity' => 4],
            'attributes' => $attributes,
        ];
    }

    /**
     * Produit tel que le renvoie la liste d'une catégorie (GET /categories/{slug}/products).
     *
     * @return array<string, mixed>
     */
    private static function listItem(string $id, string $slug): array
    {
        return [
            'id' => $id,
            'name' => 'Produit '.$slug,
            'active' => true,
            'sku' => 'SKU-'.$slug,
            'mpn' => null,
            'ean13' => null,
            'promote' => false,
            'discountPrice' => null,
            'discountAmount' => null,
            'slug' => $slug,
            'price' => 207.42,
            'thumbnail' => '/images/products/'.$slug.'/thumbnail.webp',
            'shortDescription' => 'Carte mère AM5',
            'brand' => ['id' => 'brand-msi', 'name' => 'MSI', 'slug' => 'msi', 'active' => true, 'productsCount' => 12],
            'stock' => ['quantity' => 7],
        ];
    }
}
