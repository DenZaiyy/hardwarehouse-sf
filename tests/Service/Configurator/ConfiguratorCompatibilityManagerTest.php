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

        yield 'ventirad au socket du processeur' => [['cpu' => self::product(['socket' => 'AM5', 'tdp' => 120]), 'cooler' => self::airCooler(['sockets_supported' => ['AM4', 'AM5'], 'cooler_tdp_max' => 220])], []];
        yield 'ventirad sans ce socket' => [['cpu' => self::product(['socket' => 'LGA1851', 'tdp' => 120]), 'cooler' => self::airCooler(['sockets_supported' => ['AM5'], 'cooler_tdp_max' => 220])], ['cooler_socket']];
        yield 'ventirad juste suffisant' => [['cpu' => self::product(['socket' => 'AM5', 'tdp' => 170]), 'cooler' => self::airCooler(['sockets_supported' => ['AM5'], 'cooler_tdp_max' => 170])], []];
        yield 'ventirad trop faible' => [['cpu' => self::product(['socket' => 'AM5', 'tdp' => 170]), 'cooler' => self::airCooler(['sockets_supported' => ['AM5'], 'cooler_tdp_max' => 150])], ['cooler_capacity']];

        // Aucun fabricant ne publie la puissance dissipable d'un watercooling : seul son socket compte face au processeur
        yield 'watercooling sans puissance dissipable publiée' => [['cpu' => self::product(['socket' => 'AM5', 'tdp' => 170]), 'cooler' => self::liquidCooler(['sockets_supported' => ['AM5'], 'radiator_size' => '360'])], []];
        yield 'watercooling sans ce socket' => [['cpu' => self::product(['socket' => 'LGA1851', 'tdp' => 125]), 'cooler' => self::liquidCooler(['sockets_supported' => ['AM5'], 'radiator_size' => '360'])], ['cooler_socket']];

        yield 'ventirad à la hauteur maximale du boîtier' => [['cooler' => self::airCooler(['hauteur_mm' => 165]), 'case' => self::product(['cooler_height_max' => 165])], []];
        yield 'ventirad trop haut pour le boîtier' => [['cooler' => self::airCooler(['hauteur_mm' => 168]), 'case' => self::product(['cooler_height_max' => 165])], ['cooler_height']];
        yield 'hauteur maximale du boîtier inconnue' => [['cooler' => self::airCooler(['hauteur_mm' => 154]), 'case' => self::product()], ['cooler_height']];
        yield 'la hauteur ne concerne pas un watercooling' => [['cooler' => self::liquidCooler(['radiator_size' => '240']), 'case' => self::product(['cooler_height_max' => 160, 'radiator_sizes_supported' => ['240']])], []];

        yield 'radiateur accepté par le boîtier' => [['cooler' => self::liquidCooler(['radiator_size' => '360']), 'case' => self::product(['radiator_sizes_supported' => ['240', '360']])], []];
        yield 'radiateur refusé par le boîtier' => [['cooler' => self::liquidCooler(['radiator_size' => '360']), 'case' => self::product(['radiator_sizes_supported' => ['240', '280']])], ['radiator_size']];
        yield 'radiateurs du boîtier inconnus' => [['cooler' => self::liquidCooler(['radiator_size' => '240']), 'case' => self::product(['cooler_height_max' => 165])], ['radiator_size']];
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
        self::assertSame([], (new ConfiguratorCompatibilityManager())->filtersFor(ComponentSlot::GPU, 'cartes-graphiques', []));
    }

    public function testACategoryOfAnotherSlotIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ConfiguratorCompatibilityManager())->filtersFor(ComponentSlot::COOLER, 'boitiers', []);
    }

    public function testMotherboardListFollowsTheCpuAndTheRam(): void
    {
        $filters = (new ConfiguratorCompatibilityManager())->filtersFor(ComponentSlot::MOTHERBOARD, 'cartes-meres', [
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
            $manager->filtersFor(ComponentSlot::COOLER, ComponentSlot::AIR_COOLING, ['cpu' => self::product(['socket' => 'AM5', 'tdp' => 120])]),
        );
        self::assertSame(
            ['attr[ddr_type]' => 'DDR4,DDR5'],
            $manager->filtersFor(ComponentSlot::RAM, 'memoire-ram', ['cpu' => self::product(['ddr_supported' => ['DDR4', 'DDR5']])]),
        );
        self::assertSame(
            ['attrHas[form_factors_supported]' => 'ATX'],
            $manager->filtersFor(ComponentSlot::CASE, 'boitiers', ['motherboard' => self::product(['form_factor' => 'ATX'])]),
        );
    }

    public function testEachCoolingFamilyIsListedWithItsOwnRules(): void
    {
        $manager = new ConfiguratorCompatibilityManager();
        $cpu = self::product(['socket' => 'AM5', 'tdp' => 120]);
        $case = self::product(['cooler_height_max' => 165, 'radiator_sizes_supported' => ['240', '360']]);

        self::assertSame(['attrHas[sockets_supported]' => 'AM5'], $manager->filtersFor(ComponentSlot::COOLER, ComponentSlot::LIQUID_COOLING, ['cpu' => $cpu]));
        self::assertSame(['attrMax[hauteur_mm]' => '165'], $manager->filtersFor(ComponentSlot::COOLER, ComponentSlot::AIR_COOLING, ['case' => $case]));
        self::assertSame(['attr[radiator_size]' => '240,360'], $manager->filtersFor(ComponentSlot::COOLER, ComponentSlot::LIQUID_COOLING, ['case' => $case]));
    }

    public function testTheCaseListFollowsTheFamilyOfTheChosenCooler(): void
    {
        $manager = new ConfiguratorCompatibilityManager();

        self::assertSame(
            ['attrMin[cooler_height_max]' => '168'],
            $manager->filtersFor(ComponentSlot::CASE, 'boitiers', ['cooler' => self::airCooler(['hauteur_mm' => 168])]),
        );
        self::assertSame(
            ['attrHas[radiator_sizes_supported]' => '360'],
            $manager->filtersFor(ComponentSlot::CASE, 'boitiers', ['cooler' => self::liquidCooler(['radiator_size' => '360'])]),
        );
    }

    public function testTheCpuListIgnoresTheCapacityOfAWatercooling(): void
    {
        $filters = (new ConfiguratorCompatibilityManager())->filtersFor(ComponentSlot::CPU, 'processeurs', [
            'cooler' => self::liquidCooler(['sockets_supported' => ['AM5'], 'radiator_size' => '360']),
        ]);

        self::assertSame(['attr[socket]' => 'AM5'], $filters);
    }

    public function testConstraintsOnTheSameAttributeAreCombined(): void
    {
        $filters = (new ConfiguratorCompatibilityManager())->filtersFor(ComponentSlot::CPU, 'processeurs', [
            'motherboard' => self::product(['socket' => 'AM5']),
            'cooler' => self::airCooler(['sockets_supported' => ['AM4', 'AM5'], 'cooler_tdp_max' => 150]),
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
            $manager->filtersFor(ComponentSlot::PSU, 'alimentations', ['cpu' => $cpu, 'gpu' => self::product(['power_draw' => 300])]),
        );
        // 850 / 1,3 - 100 - 120 = 433,8 W pour la carte graphique
        self::assertSame(
            ['attrMax[power_draw]' => '433'],
            $manager->filtersFor(ComponentSlot::GPU, 'cartes-graphiques', ['cpu' => $cpu, 'psu' => self::product(['psu_power' => 850])]),
        );
    }

    public function testNothingFitsWhenAChosenComponentLacksTheAttribute(): void
    {
        self::assertNull((new ConfiguratorCompatibilityManager())->filtersFor(ComponentSlot::MOTHERBOARD, 'cartes-meres', ['cpu' => self::product()]));
    }

    public function testNothingFitsWhenChosenComponentsContradictEachOther(): void
    {
        $filters = (new ConfiguratorCompatibilityManager())->filtersFor(ComponentSlot::CPU, 'processeurs', [
            'motherboard' => self::product(['socket' => 'AM5']),
            'cooler' => self::airCooler(['sockets_supported' => ['LGA1851'], 'cooler_tdp_max' => 150]),
        ]);

        self::assertNull($filters);
    }

    /**
     * @param array<string, string|int|float|bool|list<string>> $attributes
     */
    private static function airCooler(array $attributes = []): ProductSpecDto
    {
        return self::product($attributes, ComponentSlot::AIR_COOLING);
    }

    /**
     * @param array<string, string|int|float|bool|list<string>> $attributes
     */
    private static function liquidCooler(array $attributes = []): ProductSpecDto
    {
        return self::product($attributes, ComponentSlot::LIQUID_COOLING);
    }
}
