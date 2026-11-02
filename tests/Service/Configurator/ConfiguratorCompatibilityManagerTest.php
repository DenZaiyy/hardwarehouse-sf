<?php

declare(strict_types=1);

namespace App\Tests\Service\Configurator;

use App\DTO\Api\Products\ProductSpecDto;
use App\DTO\Configurator\ConfiguratorIssue;
use App\Enum\ComponentSlot;
use App\Service\Configurator\ConfiguratorCompatibilityManager;
use App\Tests\Support\BuildsProductSpecs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfiguratorCompatibilityManagerTest extends TestCase
{
    use BuildsProductSpecs;

    /**
     * Chaque règle : une paire compatible, une paire incompatible, un attribut manquant.
     *
     * @return iterable<string, array{array<string, ProductSpecDto>, list<string>}>
     */
    public static function selections(): iterable
    {
        yield 'socket commun' => [['cpu' => self::product(['socket' => 'AM5']), 'motherboard' => self::product(['socket' => 'AM5'])], []];
        yield 'sockets différents' => [['cpu' => self::product(['socket' => 'AM5']), 'motherboard' => self::product(['socket' => 'AM4'])], ['socket']];
        yield 'socket inconnu' => [['cpu' => self::product(), 'motherboard' => self::product(['socket' => 'AM5'])], ['socket']];

        yield 'même DDR que la carte mère' => [['ram' => self::product(['ddr_type' => 'DDR5']), 'motherboard' => self::product(['ddr_type' => 'DDR5'])], []];
        yield 'autre DDR que la carte mère' => [['ram' => self::product(['ddr_type' => 'DDR4']), 'motherboard' => self::product(['ddr_type' => 'DDR5'])], ['memory_type']];

        yield 'DDR gérée par le processeur' => [['ram' => self::product(['ddr_type' => 'DDR5']), 'cpu' => self::product(['ddr_supported' => ['DDR4', 'DDR5']])], []];
        yield 'DDR refusée par le processeur' => [['ram' => self::product(['ddr_type' => 'DDR5']), 'cpu' => self::product(['ddr_supported' => ['DDR4']])], ['memory_support']];

        yield 'format accepté par le boîtier' => [['motherboard' => self::product(['form_factor' => 'Micro-ATX']), 'case' => self::product(['form_factors_supported' => ['ATX', 'Micro-ATX']])], []];
        yield 'format refusé par le boîtier' => [['motherboard' => self::product(['form_factor' => 'ATX']), 'case' => self::product(['form_factors_supported' => ['Mini-ITX']])], ['form_factor']];
        yield 'formats du boîtier inconnus' => [['motherboard' => self::product(['form_factor' => 'ATX']), 'case' => self::product()], ['form_factor']];

        yield 'refroidissement au socket du processeur' => [['cpu' => self::product(['socket' => 'AM5', 'tdp' => 120]), 'cooler' => self::product(['sockets_supported' => ['AM4', 'AM5'], 'cooler_tdp_max' => 220])], []];
        yield 'refroidissement sans ce socket' => [['cpu' => self::product(['socket' => 'LGA1851', 'tdp' => 120]), 'cooler' => self::product(['sockets_supported' => ['AM5'], 'cooler_tdp_max' => 220])], ['cooler_socket']];
        yield 'refroidissement juste suffisant' => [['cpu' => self::product(['socket' => 'AM5', 'tdp' => 170]), 'cooler' => self::product(['sockets_supported' => ['AM5'], 'cooler_tdp_max' => 170])], []];
        yield 'refroidissement trop faible' => [['cpu' => self::product(['socket' => 'AM5', 'tdp' => 170]), 'cooler' => self::product(['sockets_supported' => ['AM5'], 'cooler_tdp_max' => 150])], ['cooler_capacity']];
    }

    /**
     * @param array<string, ProductSpecDto> $selection
     * @param list<string>                  $expectedRules
     */
    #[DataProvider('selections')]
    public function testRulesApplyBetweenChosenComponents(array $selection, array $expectedRules): void
    {
        $rules = array_map(static fn (ConfiguratorIssue $issue): string => $issue->rule, (new ConfiguratorCompatibilityManager())->violations($selection));

        self::assertSame($expectedRules, $rules);
    }

    public function testPowerSupplyMustCoverCpuAndGpuWithItsMargin(): void
    {
        $manager = new ConfiguratorCompatibilityManager();
        // (120 + 300 + 100) × 1,3 = 676 W
        $selection = ['cpu' => self::product(['tdp' => 120]), 'gpu' => self::product(['power_draw' => 300])];

        self::assertSame([], $manager->violations([...$selection, 'psu' => self::product(['psu_power' => 676])]));

        $violations = $manager->violations([...$selection, 'psu' => self::product(['psu_power' => 675])]);
        self::assertCount(1, $violations);
        self::assertSame('power', $violations[0]->rule);
        self::assertSame([ComponentSlot::PSU, ComponentSlot::CPU, ComponentSlot::GPU], $violations[0]->slots);
    }

    public function testPowerSupplyWithoutKnownPowerIsIncompatible(): void
    {
        $violations = (new ConfiguratorCompatibilityManager())->violations(['cpu' => self::product(['tdp' => 65]), 'psu' => self::product()]);

        self::assertSame(['power'], array_map(static fn (ConfiguratorIssue $issue): string => $issue->rule, $violations));
    }

    public function testNoFilterWithoutChosenComponent(): void
    {
        self::assertSame([], (new ConfiguratorCompatibilityManager())->filtersFor(ComponentSlot::GPU, []));
    }

    public function testMotherboardListFollowsTheCpuAndTheRam(): void
    {
        $filters = (new ConfiguratorCompatibilityManager())->filtersFor(ComponentSlot::MOTHERBOARD, [
            'cpu' => self::product(['socket' => 'AM5']),
            'ram' => self::product(['ddr_type' => 'DDR5']),
        ]);

        self::assertSame(['attr[socket]' => 'AM5', 'attr[ddr_type]' => 'DDR5'], $filters);
    }

    public function testEachRuleAlsoFiltersInTheOtherDirection(): void
    {
        $manager = new ConfiguratorCompatibilityManager();

        self::assertSame(
            ['attrHas[sockets_supported]' => 'AM5', 'attrMin[cooler_tdp_max]' => '120'],
            $manager->filtersFor(ComponentSlot::COOLER, ['cpu' => self::product(['socket' => 'AM5', 'tdp' => 120])]),
        );
        self::assertSame(
            ['attr[ddr_type]' => 'DDR4,DDR5'],
            $manager->filtersFor(ComponentSlot::RAM, ['cpu' => self::product(['ddr_supported' => ['DDR4', 'DDR5']])]),
        );
        self::assertSame(
            ['attrHas[form_factors_supported]' => 'ATX'],
            $manager->filtersFor(ComponentSlot::CASE, ['motherboard' => self::product(['form_factor' => 'ATX'])]),
        );
    }

    public function testConstraintsOnTheSameAttributeAreCombined(): void
    {
        $filters = (new ConfiguratorCompatibilityManager())->filtersFor(ComponentSlot::CPU, [
            'motherboard' => self::product(['socket' => 'AM5']),
            'cooler' => self::product(['sockets_supported' => ['AM4', 'AM5'], 'cooler_tdp_max' => 150]),
            // 650 / 1,3 - 100 - 200 = 200 W pour le processeur, mais le refroidissement n'en dissipe que 150
            'psu' => self::product(['psu_power' => 650]),
            'gpu' => self::product(['power_draw' => 200]),
        ]);

        self::assertSame(['attr[socket]' => 'AM5', 'attrMax[tdp]' => '150'], $filters);
    }

    public function testPowerBudgetFiltersThePowerSupplyAndTheGpu(): void
    {
        $manager = new ConfiguratorCompatibilityManager();
        $cpu = self::product(['tdp' => 120]);

        self::assertSame(
            ['attrMin[psu_power]' => '676'],
            $manager->filtersFor(ComponentSlot::PSU, ['cpu' => $cpu, 'gpu' => self::product(['power_draw' => 300])]),
        );
        // 850 / 1,3 - 100 - 120 = 433,8 W pour la carte graphique
        self::assertSame(
            ['attrMax[power_draw]' => '433'],
            $manager->filtersFor(ComponentSlot::GPU, ['cpu' => $cpu, 'psu' => self::product(['psu_power' => 850])]),
        );
    }

    public function testNothingFitsWhenAChosenComponentLacksTheAttribute(): void
    {
        self::assertNull((new ConfiguratorCompatibilityManager())->filtersFor(ComponentSlot::MOTHERBOARD, ['cpu' => self::product()]));
    }

    public function testNothingFitsWhenChosenComponentsContradictEachOther(): void
    {
        $filters = (new ConfiguratorCompatibilityManager())->filtersFor(ComponentSlot::CPU, [
            'motherboard' => self::product(['socket' => 'AM5']),
            'cooler' => self::product(['sockets_supported' => ['LGA1851'], 'cooler_tdp_max' => 150]),
        ]);

        self::assertNull($filters);
    }
}
