<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\Configurator\ConfiguratorIssue;
use App\DTO\Configurator\ConfiguratorState;
use App\Entity\Configuration;
use App\Entity\ConfigurationItem;
use App\Enum\ComponentSlot;
use App\Service\CartService;
use App\Service\ConfigurationService;
use App\Tests\Http\ApiMockResponseFactory;
use App\Tests\Support\CreatesShopEntities;
use App\Tests\Support\FakesCatalogApi;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Les composants sont relus dans l'API avant d'être sauvegardés ou ajoutés au panier. Une configuration incomplète
 * peut être sauvegardée, jamais une configuration incompatible, et seule une configuration complète, compatible et
 * en stock part au panier, entière.
 */
final class ConfigurationServiceTest extends KernelTestCase
{
    use CreatesShopEntities;
    use FakesCatalogApi;

    /** @var list<array<string, mixed>> fiches que renvoie la lecture groupée simulée */
    private array $specs = [];

    protected function setUp(): void
    {
        ApiMockResponseFactory::reset();

        // Session d'un visiteur : CartService y range le jeton de son panier
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = static::getContainer()->get(RequestStack::class);
        self::assertInstanceOf(RequestStack::class, $requestStack);
        $requestStack->push($request);
    }

    public function testIncompleteSelectionIsSavedWithItsSnapshots(): void
    {
        $state = new ConfiguratorState();
        $this->choose($state, ComponentSlot::CPU, 'processeurs', self::AM5_CPU);
        $ram = $this->choose($state, ComponentSlot::RAM, 'memoire-ram', ['ddr_type' => ['value' => 'DDR5']]);
        $state->setQuantity(ComponentSlot::RAM, $ram, 2);
        $this->choose($state, ComponentSlot::STORAGE, 'ssd');
        $this->choose($state, ComponentSlot::STORAGE, 'disques-durs');
        $configuration = $this->newConfiguration();

        self::assertSame([], $this->service()->save($configuration, $state));

        $items = $this->savedItems($configuration);
        self::assertCount(4, $items);
        $memory = $items[$ram];
        self::assertSame(ComponentSlot::RAM, $memory->getSlot());
        self::assertSame(2, $memory->getQuantity());
        self::assertStringStartsWith('Produit ram-', (string) $memory->getProductNameSnapshot());
        self::assertStringStartsWith('ram-', (string) $memory->getProductSlugSnapshot());
        self::assertSame('/images/products/'.$memory->getProductSlugSnapshot().'/thumbnail.webp', $memory->getProductImageSnapshot());
        self::assertSame('374.92', $memory->getUnitPriceSnapshot());
    }

    public function testIncompatibleSelectionIsNotSaved(): void
    {
        $state = new ConfiguratorState();
        $this->choose($state, ComponentSlot::CPU, 'processeurs', self::AM5_CPU);
        $this->choose($state, ComponentSlot::MOTHERBOARD, 'cartes-meres', ['socket' => ['value' => 'LGA1700'], 'ddr_type' => ['value' => 'DDR5']]);
        $configuration = $this->newConfiguration();

        $issues = $this->service()->save($configuration, $state);

        self::assertSame(['socket'], self::rules($issues));
        self::assertNull($configuration->getId());
    }

    public function testComponentsWithdrawnDeletedOrInTheWrongSlotAreNotSaved(): void
    {
        $state = new ConfiguratorState();
        $this->choose($state, ComponentSlot::CPU, 'processeurs', self::AM5_CPU, active: false);
        $state->choose(ComponentSlot::RAM, self::productId('ram-deleted-'.bin2hex(random_bytes(4))));
        $this->choose($state, ComponentSlot::CASE, 'ecrans');
        $configuration = $this->newConfiguration();

        $issues = $this->service()->save($configuration, $state);

        self::assertSame([[ComponentSlot::CPU], [ComponentSlot::RAM], [ComponentSlot::CASE]], array_map(static fn (ConfiguratorIssue $issue): array => $issue->slots, $issues));
        self::assertSame(['unavailable', 'unavailable', 'unavailable'], self::rules($issues));
        self::assertNull($configuration->getId());
    }

    public function testSavingAgainReplacesTheComponents(): void
    {
        $configuration = $this->newConfiguration();
        $first = new ConfiguratorState();
        $this->choose($first, ComponentSlot::CPU, 'processeurs', self::AM5_CPU);
        $this->service()->save($configuration, $first);

        $second = new ConfiguratorState();
        $cpu = $this->choose($second, ComponentSlot::CPU, 'processeurs', self::AM5_CPU);
        self::assertSame([], $this->service()->save($configuration, $second));

        self::assertSame([$cpu], array_keys($this->savedItems($configuration)));
        self::assertNotNull($configuration->getUpdatedAt());
    }

    public function testDiscountedComponentIsSavedAtTheCartPrice(): void
    {
        $state = new ConfiguratorState();
        $cpu = $this->choose($state, ComponentSlot::CPU, 'processeurs', self::AM5_CPU, discountPrice: 329.92);
        $configuration = $this->newConfiguration();

        self::assertSame([], $this->service()->save($configuration, $state));

        self::assertSame('329.92', $this->savedItems($configuration)[$cpu]->getUnitPriceSnapshot());
    }

    public function testSavedSelectionGoesOnEditingItsConfiguration(): void
    {
        $state = new ConfiguratorState();
        $this->choose($state, ComponentSlot::CPU, 'processeurs', self::AM5_CPU);
        $configuration = $this->newConfiguration();

        $this->service()->save($configuration, $state);

        self::assertNotNull($configuration->getId());
        self::assertSame($configuration->getId(), $state->configurationId());
    }

    public function testOpenedConfigurationIsTheOneTheSelectionEdits(): void
    {
        $configuration = $this->newConfiguration();
        $state = new ConfiguratorState();
        $this->choose($state, ComponentSlot::CPU, 'processeurs', self::AM5_CPU);
        $this->service()->save($configuration, $state);

        self::assertSame($configuration->getId(), $this->service()->load($configuration)->configurationId());
    }

    public function testReviewReadsTheComponentsAndListsWhatKeepsThemOutOfTheCart(): void
    {
        $state = new ConfiguratorState();
        $cpu = $this->choose($state, ComponentSlot::CPU, 'processeurs', self::AM5_CPU);
        $motherboard = $this->choose($state, ComponentSlot::MOTHERBOARD, 'cartes-meres', ['socket' => ['value' => 'LGA1700'], 'ddr_type' => ['value' => 'DDR5']]);
        $screen = $this->choose($state, ComponentSlot::STORAGE, 'ecrans');

        $review = $this->service()->review($state);

        self::assertEqualsCanonicalizing([$cpu, $motherboard, $screen], array_keys($review['products']));
        // Écran à la place d'un disque, sockets différents, puis mémoire, carte graphique, boîtier et alimentation
        self::assertSame(['unavailable', 'socket', 'missing', 'missing', 'missing', 'missing'], self::rules($review['issues']));
    }

    public function testOpeningASavedConfigurationRestoresTheSelection(): void
    {
        $state = new ConfiguratorState();
        $this->choose($state, ComponentSlot::CPU, 'processeurs', self::AM5_CPU);
        $ram = $this->choose($state, ComponentSlot::RAM, 'memoire-ram', ['ddr_type' => ['value' => 'DDR5']]);
        $state->setQuantity(ComponentSlot::RAM, $ram, 2);
        $this->choose($state, ComponentSlot::STORAGE, 'ssd');
        $configuration = $this->newConfiguration();
        $this->service()->save($configuration, $state);
        $this->entityManager()->clear();

        $saved = $this->entityManager()->find(Configuration::class, $configuration->getId());
        self::assertNotNull($saved);

        $restored = $this->service()->load($saved);
        foreach (ComponentSlot::cases() as $slot) {
            self::assertSame($state->lines($slot), $restored->lines($slot), $slot->value);
        }
    }

    public function testCompleteConfigurationGoesToTheCartComponentByComponent(): void
    {
        $state = $this->completeBuild();

        self::assertSame([], $this->service()->addToCart($state));

        $expected = [];
        foreach (ComponentSlot::cases() as $slot) {
            foreach ($state->lines($slot) as $line) {
                $expected[$line['productId']] = $line['quantity'];
            }
        }
        $inCart = $this->quantitiesInCart();
        ksort($expected);
        ksort($inCart);
        self::assertCount(7, $expected);
        self::assertSame($expected, $inCart);
    }

    public function testIncompleteConfigurationStaysOutOfTheCart(): void
    {
        $state = new ConfiguratorState();
        $this->choose($state, ComponentSlot::CPU, 'processeurs', self::AM5_CPU);

        self::assertContains('missing', self::rules($this->service()->addToCart($state)));
        self::assertSame([], $this->quantitiesInCart());
    }

    public function testIncompatibleConfigurationStaysOutOfTheCart(): void
    {
        $state = $this->completeBuild();
        $this->choose($state, ComponentSlot::MOTHERBOARD, 'cartes-meres', ['socket' => ['value' => 'LGA1700'], 'ddr_type' => ['value' => 'DDR5'], 'form_factor' => ['value' => 'ATX'], 'memory_slots' => ['value' => '4', 'numericValue' => 4]]);

        self::assertSame(['socket'], self::rules($this->service()->addToCart($state)));
        self::assertSame([], $this->quantitiesInCart());
    }

    public function testOneComponentShortOfStockKeepsTheWholeConfigurationOutOfTheCart(): void
    {
        $state = $this->completeBuild();
        $ram = $this->choose($state, ComponentSlot::RAM, 'memoire-ram', ['ddr_type' => ['value' => 'DDR5']], stock: 1);
        $state->setQuantity(ComponentSlot::RAM, $ram, 2);

        $issues = $this->service()->addToCart($state);

        self::assertSame(['stock'], self::rules($issues));
        self::assertSame([ComponentSlot::RAM], $issues[0]->slots);
        self::assertSame([], $this->quantitiesInCart());
    }

    public function testWithdrawnComponentStaysOutOfTheCart(): void
    {
        $state = $this->completeBuild();
        $this->choose($state, ComponentSlot::GPU, 'cartes-graphiques', ['power_draw' => ['value' => '220', 'numericValue' => 220]], active: false);

        self::assertSame(['unavailable'], self::rules($this->service()->addToCart($state)));
        self::assertSame([], $this->quantitiesInCart());
    }

    /** Processeur AM5 qui accepte la DDR5, sans partie graphique, livré avec son ventirad */
    private const array AM5_CPU = [
        'socket' => ['value' => 'AM5'],
        'ddr_supported' => ['value' => 'DDR5', 'values' => ['DDR5']],
        'tdp' => ['value' => '120', 'numericValue' => 120],
        'integrated_graphics' => ['value' => 'false'],
        'cooler_included' => ['value' => 'true'],
    ];

    /**
     * PC complet et compatible, sans refroidissement puisque le processeur fournit le sien : (120 + 220 + 100) × 1,3
     * = 572 W, couverts par l'alimentation de 750 W.
     */
    private function completeBuild(): ConfiguratorState
    {
        $state = new ConfiguratorState();
        $this->choose($state, ComponentSlot::CPU, 'processeurs', self::AM5_CPU);
        $this->choose($state, ComponentSlot::MOTHERBOARD, 'cartes-meres', ['socket' => ['value' => 'AM5'], 'ddr_type' => ['value' => 'DDR5'], 'form_factor' => ['value' => 'ATX'], 'memory_slots' => ['value' => '4', 'numericValue' => 4]]);
        $ram = $this->choose($state, ComponentSlot::RAM, 'memoire-ram', ['ddr_type' => ['value' => 'DDR5']]);
        $state->setQuantity(ComponentSlot::RAM, $ram, 2);
        $this->choose($state, ComponentSlot::GPU, 'cartes-graphiques', ['power_draw' => ['value' => '220', 'numericValue' => 220]]);
        $this->choose($state, ComponentSlot::STORAGE, 'ssd');
        $this->choose($state, ComponentSlot::CASE, 'boitiers', ['form_factors_supported' => ['value' => 'ATX', 'values' => ['ATX', 'Micro-ATX']]]);
        $this->choose($state, ComponentSlot::PSU, 'alimentations', ['psu_power' => ['value' => '750', 'numericValue' => 750]]);

        return $state;
    }

    /**
     * Ajoute le produit au catalogue simulé, dans la lecture groupée comme dans la fiche que relit CartService, puis le
     * choisit pour l'emplacement.
     *
     * @param array<string, array{value: string, values?: list<string>, numericValue?: float|int}> $attributes
     */
    private function choose(ConfiguratorState $state, ComponentSlot $slot, string $category, array $attributes = [], bool $active = true, int $stock = 5, ?float $discountPrice = null): string
    {
        $slug = $slot->value.'-'.bin2hex(random_bytes(4));
        $discountAmount = null === $discountPrice ? null : round(374.92 - $discountPrice, 2);
        $id = $this->apiHasProduct($slug, active: $active, stock: $stock, price: 374.92, discountPrice: $discountPrice, discountAmount: $discountAmount);
        $this->specs[] = [
            'id' => $id,
            'name' => 'Produit '.$slug,
            'slug' => $slug,
            'price' => 374.92,
            'discountPrice' => $discountPrice,
            'discountAmount' => $discountAmount,
            'promote' => null !== $discountPrice,
            'active' => $active,
            'thumbnail' => '/images/products/'.$slug.'/thumbnail.webp',
            'category' => ['slug' => $category],
            'stock' => ['quantity' => $stock],
            'attributes' => $attributes,
        ];
        ApiMockResponseFactory::respondWith('products', ['data' => $this->specs, 'total' => \count($this->specs)]);
        $state->choose($slot, $id);

        return $id;
    }

    private function newConfiguration(): Configuration
    {
        return (new Configuration())->setName('Poste de jeu')->setUser($this->createUser());
    }

    /**
     * @return array<string, ConfigurationItem> composants enregistrés en base, indexés par produit
     */
    private function savedItems(Configuration $configuration): array
    {
        $this->entityManager()->clear();
        $items = [];
        foreach ($this->entityManager()->getRepository(ConfigurationItem::class)->findBy(['configuration' => $configuration->getId()]) as $item) {
            $items[(string) $item->getProductId()] = $item;
        }

        return $items;
    }

    /**
     * @return array<string, int> quantités du panier du visiteur, par produit
     */
    private function quantitiesInCart(): array
    {
        $cartService = static::getContainer()->get(CartService::class);
        self::assertInstanceOf(CartService::class, $cartService);

        return array_column($cartService->getCart(), 'quantity', 'productId');
    }

    private function service(): ConfigurationService
    {
        $service = static::getContainer()->get(ConfigurationService::class);
        self::assertInstanceOf(ConfigurationService::class, $service);

        return $service;
    }

    /**
     * @param list<ConfiguratorIssue> $issues
     *
     * @return list<string>
     */
    private static function rules(array $issues): array
    {
        return array_map(static fn (ConfiguratorIssue $issue): string => $issue->rule, $issues);
    }
}
