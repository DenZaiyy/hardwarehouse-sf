<?php

declare(strict_types=1);

namespace App\Service\Configurator;

use App\DTO\Api\Products\ProductSpecDto;
use App\DTO\Configurator\ConfiguratorIssue;
use App\Enum\ComponentSlot;

/**
 * Complétude d'une configuration : emplacements obligatoires, nombre de références et quantités. Distincte de la
 * compatibilité : une configuration incomplète peut être sauvegardée, une configuration incompatible ne le peut pas.
 */
final readonly class ConfiguratorCompletenessManager
{
    /** Barrettes admises tant que la carte mère, qui fixe le nombre d'emplacements mémoire, n'est pas connue */
    private const int DEFAULT_MEMORY_SLOTS = 4;

    /**
     * @param array<string, list<array{product: ProductSpecDto, quantity: int}>> $selection lignes choisies, par emplacement
     *
     * @return list<ConfiguratorIssue>
     */
    public function issues(array $selection): array
    {
        $cpu = ($selection[ComponentSlot::CPU->value] ?? [])[0]['product'] ?? null;
        $issues = [];

        foreach (ComponentSlot::cases() as $slot) {
            $lines = $selection[$slot->value] ?? [];
            if ([] === $lines) {
                if ($this->isRequired($slot, $cpu)) {
                    $issues[] = new ConfiguratorIssue([$slot], 'missing');
                }
                continue;
            }

            if (\count($lines) > $slot->maxReferences()) {
                $issues[] = new ConfiguratorIssue([$slot], 'references');
            }

            $maxQuantity = $this->maxQuantity($slot, $selection);
            foreach ($lines as $line) {
                if ($line['quantity'] < 1 || $line['quantity'] > $maxQuantity) {
                    $issues[] = new ConfiguratorIssue([$slot], 'quantity');
                    break;
                }
            }
        }

        return $issues;
    }

    /** La carte graphique et le refroidissement deviennent facultatifs quand le processeur les fournit. */
    private function isRequired(ComponentSlot $slot, ?ProductSpecDto $cpu): bool
    {
        return match ($slot) {
            ComponentSlot::GPU => !($cpu?->flag('integrated_graphics') ?? false),
            ComponentSlot::COOLER => !($cpu?->flag('cooler_included') ?? false),
            default => true,
        };
    }

    /**
     * Seule la mémoire se prend en plusieurs exemplaires, autant que la carte mère a d'emplacements.
     *
     * @param array<string, list<array{product: ProductSpecDto, quantity: int}>> $selection
     */
    private function maxQuantity(ComponentSlot $slot, array $selection): int
    {
        if (ComponentSlot::RAM !== $slot) {
            return 1;
        }

        $motherboard = ($selection[ComponentSlot::MOTHERBOARD->value] ?? [])[0]['product'] ?? null;
        $memorySlots = $motherboard?->number('memory_slots');

        return null === $memorySlots ? self::DEFAULT_MEMORY_SLOTS : (int) $memorySlots;
    }
}
