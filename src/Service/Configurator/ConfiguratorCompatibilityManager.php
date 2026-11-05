<?php

declare(strict_types=1);

namespace App\Service\Configurator;

use App\DTO\Api\Products\ProductSpecDto;
use App\DTO\Configurator\ConfiguratorIssue;
use App\Enum\ComponentSlot;

/**
 * Règles de compatibilité du configurateur, sans HTTP ni base de données. filtersFor() traduit la sélection en filtres
 * d'attributs de l'API pour ne lister que des produits compatibles, violations() revalide une configuration avant sa
 * sauvegarde ou son ajout au panier.
 *
 * Une règle porte sur une paire d'emplacements, sans sens imposé : le client commence par le composant qu'il veut.
 * Un produit dépourvu d'un attribut exigé par une règle est incompatible.
 */
final readonly class ConfiguratorCompatibilityManager
{
    /** Consommation estimée du reste de la machine (carte mère, mémoire, stockage, ventilation), en watts */
    public const int BASE_CONSUMPTION_W = 100;

    /** Réserve de l'alimentation au-delà de la consommation estimée (pics de charge, vieillissement) */
    public const float SAFETY_MARGIN = 1.3;

    /**
     * Règles déclarées comme données : [type, emplacement A, attribut de A, emplacement B, attribut de B].
     * match : les deux valeurs sont égales. contains : la valeur de A figure dans la liste de B. atLeast : le nombre
     * de A vaut au moins celui de B. Une nouvelle règle de l'un de ces types tient en une ligne.
     */
    private const array RULES = [
        'socket' => ['match', ComponentSlot::CPU, 'socket', ComponentSlot::MOTHERBOARD, 'socket'],
        'memory_type' => ['match', ComponentSlot::RAM, 'ddr_type', ComponentSlot::MOTHERBOARD, 'ddr_type'],
        'memory_support' => ['contains', ComponentSlot::RAM, 'ddr_type', ComponentSlot::CPU, 'ddr_supported'],
        'form_factor' => ['contains', ComponentSlot::MOTHERBOARD, 'form_factor', ComponentSlot::CASE, 'form_factors_supported'],
        'cooler_socket' => ['contains', ComponentSlot::CPU, 'socket', ComponentSlot::COOLER, 'sockets_supported'],
        'cooler_capacity' => ['atLeast', ComponentSlot::COOLER, 'cooler_tdp_max', ComponentSlot::CPU, 'tdp'],
    ];

    /**
     * Filtres d'attributs qui ne laissent dans la liste de $target que des produits compatibles avec la sélection.
     *
     * @param array<string, ProductSpecDto> $selection produits choisis, indexés par emplacement (ComponentSlot::value)
     *
     * @return array<string, string>|null paramètres de requête de l'API, ou null si aucun produit ne peut convenir
     */
    public function filtersFor(ComponentSlot $target, array $selection): ?array
    {
        $filters = new AttributeFilters();

        foreach (self::RULES as [$type, $slotA, $attributeA, $slotB, $attributeB]) {
            if ($target === $slotA && isset($selection[$slotB->value])) {
                $this->filterFirst($filters, $type, $attributeA, $selection[$slotB->value], $attributeB);
            } elseif ($target === $slotB && isset($selection[$slotA->value])) {
                $this->filterSecond($filters, $type, $attributeB, $selection[$slotA->value], $attributeA);
            }
        }

        $powerSupply = $selection[ComponentSlot::PSU->value] ?? null;
        if (ComponentSlot::PSU === $target) {
            $this->filterPowerSupply($filters, $selection);
        } elseif (ComponentSlot::CPU === $target && null !== $powerSupply) {
            $this->filterConsumer($filters, 'tdp', $powerSupply, $this->draw($selection, ComponentSlot::GPU, 'power_draw'));
        } elseif (ComponentSlot::GPU === $target && null !== $powerSupply) {
            $this->filterConsumer($filters, 'power_draw', $powerSupply, $this->draw($selection, ComponentSlot::CPU, 'tdp'));
        }

        return $filters->toQuery();
    }

    /**
     * @param array<string, ProductSpecDto> $selection
     *
     * @return list<ConfiguratorIssue>
     */
    public function violations(array $selection): array
    {
        $issues = [];
        foreach (self::RULES as $rule => [$type, $slotA, $attributeA, $slotB, $attributeB]) {
            $first = $selection[$slotA->value] ?? null;
            $second = $selection[$slotB->value] ?? null;

            if (null !== $first && null !== $second && !$this->satisfies($type, $first, $attributeA, $second, $attributeB)) {
                $issues[] = new ConfiguratorIssue([$slotA, $slotB], $rule);
            }
        }

        $powerIssue = $this->powerViolation($selection);
        if (null !== $powerIssue) {
            $issues[] = $powerIssue;
        }

        return $issues;
    }

    /**
     * @param 'match'|'contains'|'atLeast' $type
     */
    private function satisfies(string $type, ProductSpecDto $first, string $attributeA, ProductSpecDto $second, string $attributeB): bool
    {
        $value = $first->value($attributeA);
        $number = $first->number($attributeA);
        $minimum = $second->number($attributeB);

        return match ($type) {
            'match' => null !== $value && $value === $second->value($attributeB),
            'contains' => null !== $value && \in_array($value, $second->values($attributeB), true),
            'atLeast' => null !== $number && null !== $minimum && $number >= $minimum,
        };
    }

    /**
     * La liste visée occupe l'emplacement A de la règle, $chosen l'emplacement B.
     *
     * @param 'match'|'contains'|'atLeast' $type
     */
    private function filterFirst(AttributeFilters $filters, string $type, string $attribute, ProductSpecDto $chosen, string $chosenAttribute): void
    {
        $value = $chosen->value($chosenAttribute);
        $values = $chosen->values($chosenAttribute);
        $number = $chosen->number($chosenAttribute);

        match ($type) {
            'match' => null === $value ? $filters->block() : $filters->allow($attribute, [$value]),
            'contains' => [] === $values ? $filters->block() : $filters->allow($attribute, $values),
            'atLeast' => null === $number ? $filters->block() : $filters->atLeast($attribute, $number),
        };
    }

    /**
     * La liste visée occupe l'emplacement B de la règle, $chosen l'emplacement A.
     *
     * @param 'match'|'contains'|'atLeast' $type
     */
    private function filterSecond(AttributeFilters $filters, string $type, string $attribute, ProductSpecDto $chosen, string $chosenAttribute): void
    {
        $value = $chosen->value($chosenAttribute);
        $number = $chosen->number($chosenAttribute);

        match ($type) {
            'match' => null === $value ? $filters->block() : $filters->allow($attribute, [$value]),
            'contains' => null === $value ? $filters->block() : $filters->require($attribute, $value),
            'atLeast' => null === $number ? $filters->block() : $filters->atMost($attribute, $number),
        };
    }

    /**
     * L'alimentation doit couvrir la consommation du processeur et de la carte graphique déjà choisis.
     *
     * @param array<string, ProductSpecDto> $selection
     */
    private function filterPowerSupply(AttributeFilters $filters, array $selection): void
    {
        if (!isset($selection[ComponentSlot::CPU->value]) && !isset($selection[ComponentSlot::GPU->value])) {
            return;
        }

        $need = $this->powerNeed($selection);
        null === $need ? $filters->block() : $filters->atLeast('psu_power', ceil($need));
    }

    /**
     * Le processeur ou la carte graphique doit tenir dans ce que l'alimentation laisse une fois l'autre servi.
     */
    private function filterConsumer(AttributeFilters $filters, string $attribute, ProductSpecDto $powerSupply, ?float $otherDraw): void
    {
        $power = $powerSupply->number('psu_power');
        $budget = null === $power || null === $otherDraw ? null : round($power / self::SAFETY_MARGIN - self::BASE_CONSUMPTION_W - $otherDraw, 2);

        null === $budget || $budget < 0 ? $filters->block() : $filters->atMost($attribute, floor($budget));
    }

    /**
     * @param array<string, ProductSpecDto> $selection
     */
    private function powerViolation(array $selection): ?ConfiguratorIssue
    {
        $powerSupply = $selection[ComponentSlot::PSU->value] ?? null;
        $consumers = array_values(array_filter(
            [ComponentSlot::CPU, ComponentSlot::GPU],
            static fn (ComponentSlot $slot): bool => isset($selection[$slot->value]),
        ));
        if (null === $powerSupply || [] === $consumers) {
            return null;
        }

        $need = $this->powerNeed($selection);
        $power = $powerSupply->number('psu_power');
        if (null !== $need && null !== $power && $power >= $need) {
            return null;
        }

        return new ConfiguratorIssue([ComponentSlot::PSU, ...$consumers], 'power');
    }

    /**
     * Puissance nécessaire en watts : (TDP + carte graphique + reste de la machine) × marge. Arrondie au centième pour
     * qu'une imprécision de calcul ne refuse pas une alimentation tout juste suffisante.
     *
     * @param array<string, ProductSpecDto> $selection
     *
     * @return float|null null si un composant choisi n'indique pas sa consommation
     */
    private function powerNeed(array $selection): ?float
    {
        $cpu = $this->draw($selection, ComponentSlot::CPU, 'tdp');
        $gpu = $this->draw($selection, ComponentSlot::GPU, 'power_draw');

        return null === $cpu || null === $gpu ? null : round(($cpu + $gpu + self::BASE_CONSUMPTION_W) * self::SAFETY_MARGIN, 2);
    }

    /**
     * Consommation d'un emplacement : 0 s'il est vide, null si le produit choisi ne l'indique pas.
     *
     * @param array<string, ProductSpecDto> $selection
     */
    private function draw(array $selection, ComponentSlot $slot, string $attribute): ?float
    {
        $product = $selection[$slot->value] ?? null;

        return null === $product ? 0.0 : $product->number($attribute);
    }
}
