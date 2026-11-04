<?php

declare(strict_types=1);

namespace App\Tests\DTO\Configurator;

use App\DTO\Api\Products\ProductSpecDto;
use App\DTO\Configurator\ConfiguratorState;
use App\Enum\ComponentSlot;
use PHPUnit\Framework\TestCase;

/**
 * Sélection en cours du configurateur : un produit par emplacement, jusqu'à trois disques différents, et une quantité
 * que seul le client change (la mémoire). Les règles qui dépendent des caractéristiques des produits restent aux
 * services de compatibilité et de complétude.
 */
final class ConfiguratorStateTest extends TestCase
{
    public function testChoosingAnotherProcessorReplacesThePreviousOne(): void
    {
        $state = new ConfiguratorState();

        $state->choose(ComponentSlot::CPU, 'cpu-a');
        $state->choose(ComponentSlot::CPU, 'cpu-b');

        self::assertSame([['productId' => 'cpu-b', 'quantity' => 1]], $state->lines(ComponentSlot::CPU));
    }

    public function testChoosingAnotherMemoryKitStartsAgainFromOneModule(): void
    {
        $state = new ConfiguratorState();
        $state->choose(ComponentSlot::RAM, 'ram-a');
        $state->setQuantity(ComponentSlot::RAM, 'ram-a', 2);

        $state->choose(ComponentSlot::RAM, 'ram-b');

        self::assertSame([['productId' => 'ram-b', 'quantity' => 1]], $state->lines(ComponentSlot::RAM));
    }

    public function testStorageKeepsUpToThreeDifferentDrives(): void
    {
        $state = new ConfiguratorState();

        self::assertTrue($state->choose(ComponentSlot::STORAGE, 'ssd-a'));
        self::assertTrue($state->choose(ComponentSlot::STORAGE, 'ssd-b'));
        self::assertTrue($state->choose(ComponentSlot::STORAGE, 'hdd-c'));
        self::assertFalse($state->choose(ComponentSlot::STORAGE, 'hdd-d'));

        self::assertSame([
            ['productId' => 'ssd-a', 'quantity' => 1],
            ['productId' => 'ssd-b', 'quantity' => 1],
            ['productId' => 'hdd-c', 'quantity' => 1],
        ], $state->lines(ComponentSlot::STORAGE));
    }

    public function testChoosingTheSameDriveTwiceKeepsASingleLine(): void
    {
        $state = new ConfiguratorState();

        $state->choose(ComponentSlot::STORAGE, 'ssd-a');
        self::assertTrue($state->choose(ComponentSlot::STORAGE, 'ssd-a'));

        self::assertSame([['productId' => 'ssd-a', 'quantity' => 1]], $state->lines(ComponentSlot::STORAGE));
    }

    public function testQuantityCannotDropBelowOneOrApplyToAProductNotChosen(): void
    {
        $state = new ConfiguratorState();
        $state->choose(ComponentSlot::RAM, 'ram-a');

        self::assertTrue($state->setQuantity(ComponentSlot::RAM, 'ram-a', 2));
        self::assertFalse($state->setQuantity(ComponentSlot::RAM, 'ram-a', 0));
        self::assertFalse($state->setQuantity(ComponentSlot::RAM, 'ram-b', 3));

        self::assertSame([['productId' => 'ram-a', 'quantity' => 2]], $state->lines(ComponentSlot::RAM));
    }

    public function testRemovingTheLastProductEmptiesTheSelection(): void
    {
        $state = new ConfiguratorState();
        $state->choose(ComponentSlot::STORAGE, 'ssd-a');
        $state->choose(ComponentSlot::STORAGE, 'ssd-b');

        $state->remove(ComponentSlot::STORAGE, 'ssd-a');
        self::assertSame([['productId' => 'ssd-b', 'quantity' => 1]], $state->lines(ComponentSlot::STORAGE));

        $state->remove(ComponentSlot::STORAGE, 'ssd-b');
        self::assertSame([], $state->lines(ComponentSlot::STORAGE));
        self::assertTrue($state->isEmpty());
    }

    public function testProductIdsListsEveryChosenProductOnce(): void
    {
        $state = new ConfiguratorState();
        $state->choose(ComponentSlot::CPU, 'cpu-a');
        $state->choose(ComponentSlot::STORAGE, 'ssd-a');
        $state->choose(ComponentSlot::STORAGE, 'ssd-b');

        self::assertSame(['cpu-a', 'ssd-a', 'ssd-b'], $state->productIds());
    }

    public function testSelectionSurvivesTheSessionRoundTrip(): void
    {
        $state = new ConfiguratorState();
        $state->choose(ComponentSlot::CPU, 'cpu-a');
        $state->choose(ComponentSlot::RAM, 'ram-a');
        $state->setQuantity(ComponentSlot::RAM, 'ram-a', 2);

        $restored = ConfiguratorState::fromArray($state->toArray());

        self::assertSame([['productId' => 'cpu-a', 'quantity' => 1]], $restored->lines(ComponentSlot::CPU));
        self::assertSame([['productId' => 'ram-a', 'quantity' => 2]], $restored->lines(ComponentSlot::RAM));
    }

    public function testUnknownSlotsAndMalformedLinesFromAnOlderSessionAreIgnored(): void
    {
        $state = ConfiguratorState::fromArray([
            'cpu' => [['productId' => 'cpu-a', 'quantity' => 1]],
            'fan' => [['productId' => 'fan-a', 'quantity' => 1]],
            'ram' => [['productId' => 42, 'quantity' => 'two'], ['productId' => 'ram-a', 'quantity' => 0]],
            'gpu' => 'gpu-a',
        ]);

        self::assertSame(['cpu-a'], $state->productIds());
        self::assertSame([], $state->lines(ComponentSlot::RAM));
    }

    public function testCompatibilityReceivesTheFirstReadProductOfEachSlot(): void
    {
        $state = new ConfiguratorState();
        $state->choose(ComponentSlot::CPU, 'cpu-a');
        $state->choose(ComponentSlot::STORAGE, 'ssd-a');
        $state->choose(ComponentSlot::STORAGE, 'ssd-b');
        $state->choose(ComponentSlot::RAM, 'ram-deleted');
        $cpu = new ProductSpecDto(id: 'cpu-a');
        $firstDrive = new ProductSpecDto(id: 'ssd-a');

        $bySlot = $state->productsBySlot(['cpu-a' => $cpu, 'ssd-a' => $firstDrive, 'ssd-b' => new ProductSpecDto(id: 'ssd-b')]);

        self::assertSame(['cpu' => $cpu, 'storage' => $firstDrive], $bySlot);
    }

    public function testCompletenessReceivesTheQuantitiesOfReadProducts(): void
    {
        $state = new ConfiguratorState();
        $state->choose(ComponentSlot::CPU, 'cpu-deleted');
        $state->choose(ComponentSlot::RAM, 'ram-a');
        $state->setQuantity(ComponentSlot::RAM, 'ram-a', 2);
        $ram = new ProductSpecDto(id: 'ram-a');

        self::assertSame(['ram' => [['product' => $ram, 'quantity' => 2]]], $state->linesWith(['ram-a' => $ram]));
    }
}
