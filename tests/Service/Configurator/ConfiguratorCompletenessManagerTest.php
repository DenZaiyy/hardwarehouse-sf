<?php

declare(strict_types=1);

namespace App\Tests\Service\Configurator;

use App\DTO\Api\Products\ProductSpecDto;
use App\DTO\Configurator\ConfiguratorIssue;
use App\Enum\ComponentSlot;
use App\Service\Configurator\ConfiguratorCompletenessManager;
use App\Tests\Support\BuildsProductSpecs;
use PHPUnit\Framework\TestCase;

final class ConfiguratorCompletenessManagerTest extends TestCase
{
    use BuildsProductSpecs;

    public function testEveryComponentIsRequiredUntilTheCpuSaysOtherwise(): void
    {
        $missing = $this->issues([]);

        self::assertSame(array_map(static fn (ComponentSlot $slot): string => $slot->value.':missing', ComponentSlot::cases()), $missing);
    }

    public function testCompleteConfigurationHasNoIssue(): void
    {
        self::assertSame([], $this->issues(self::completeSelection()));
    }

    public function testIntegratedGraphicsAndBundledCoolerMakeGpuAndCoolerOptional(): void
    {
        $selection = self::completeSelection();
        unset($selection['gpu'], $selection['cooler']);
        $selection['cpu'] = [['product' => self::product(['integrated_graphics' => true, 'cooler_included' => true]), 'quantity' => 1]];

        self::assertSame([], $this->issues($selection));
    }

    public function testRamQuantityIsLimitedByTheMotherboardSlots(): void
    {
        $selection = self::completeSelection();
        $selection['motherboard'] = [['product' => self::product(['memory_slots' => 2]), 'quantity' => 1]];

        $selection['ram'] = [['product' => self::product(), 'quantity' => 2]];
        self::assertSame([], $this->issues($selection));

        $selection['ram'] = [['product' => self::product(), 'quantity' => 3]];
        self::assertSame(['ram:quantity'], $this->issues($selection));
    }

    public function testRamQuantityIsLimitedToFourWithoutMotherboard(): void
    {
        $selection = self::completeSelection();
        unset($selection['motherboard']);

        $selection['ram'] = [['product' => self::product(), 'quantity' => 4]];
        self::assertSame(['motherboard:missing'], $this->issues($selection));

        $selection['ram'] = [['product' => self::product(), 'quantity' => 5]];
        self::assertSame(['motherboard:missing', 'ram:quantity'], $this->issues($selection));
    }

    public function testStorageAcceptsUpToThreeReferences(): void
    {
        $selection = self::completeSelection();

        $selection['storage'] = array_fill(0, 3, ['product' => self::product(), 'quantity' => 1]);
        self::assertSame([], $this->issues($selection));

        $selection['storage'] = array_fill(0, 4, ['product' => self::product(), 'quantity' => 1]);
        self::assertSame(['storage:references'], $this->issues($selection));
    }

    public function testOtherComponentsTakeOneReferenceInOneCopy(): void
    {
        $selection = self::completeSelection();
        $selection['case'] = [['product' => self::product(), 'quantity' => 2]];
        $selection['psu'][] = ['product' => self::product(), 'quantity' => 1];

        self::assertSame(['case:quantity', 'psu:references'], $this->issues($selection));
    }

    /**
     * @param array<string, list<array{product: ProductSpecDto, quantity: int}>> $selection
     *
     * @return list<string>
     */
    private function issues(array $selection): array
    {
        return array_map(
            static fn (ConfiguratorIssue $issue): string => implode(',', array_map(static fn (ComponentSlot $slot): string => $slot->value, $issue->slots)).':'.$issue->rule,
            (new ConfiguratorCompletenessManager())->issues($selection),
        );
    }

    /**
     * @return array<string, list<array{product: ProductSpecDto, quantity: int}>>
     */
    private static function completeSelection(): array
    {
        $selection = [];
        foreach (ComponentSlot::cases() as $slot) {
            $selection[$slot->value] = [['product' => self::product(), 'quantity' => 1]];
        }
        $selection['motherboard'] = [['product' => self::product(['memory_slots' => 4]), 'quantity' => 1]];

        return $selection;
    }
}
