<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Emplacements d'une configuration de PC. La valeur est celle enregistrée dans configuration_item.slot.
 */
enum ComponentSlot: string implements TranslatableInterface
{
    case CPU = 'cpu';
    case COOLER = 'cooler';
    case MOTHERBOARD = 'motherboard';
    case RAM = 'ram';
    case GPU = 'gpu';
    case STORAGE = 'storage';
    case CASE = 'case';
    case PSU = 'psu';

    /** Ventirads : la catégorie garde l'identifiant de sa création, quand elle était le seul refroidissement */
    public const string AIR_COOLING = 'refroidissement';

    /** Watercoolings tout-en-un */
    public const string LIQUID_COOLING = 'watercooling';

    /**
     * Catégories de l'API dont les produits peuvent occuper l'emplacement.
     *
     * @return list<string>
     */
    public function categorySlugs(): array
    {
        return match ($this) {
            self::CPU => ['processeurs'],
            self::COOLER => [self::AIR_COOLING, self::LIQUID_COOLING],
            self::MOTHERBOARD => ['cartes-meres'],
            self::RAM => ['memoire-ram'],
            self::GPU => ['cartes-graphiques'],
            self::STORAGE => ['ssd', 'disques-durs'],
            self::CASE => ['boitiers'],
            self::PSU => ['alimentations'],
        };
    }

    /** Emplacement que les produits d'une catégorie de l'API peuvent occuper, ou null hors du configurateur (écrans…). */
    public static function forCategory(string $categorySlug): ?self
    {
        foreach (self::cases() as $slot) {
            if (\in_array($categorySlug, $slot->categorySlugs(), true)) {
                return $slot;
            }
        }

        return null;
    }

    /** Références différentes admises : jusqu'à trois disques, une seule référence ailleurs. */
    public function maxReferences(): int
    {
        return self::STORAGE === $this ? 3 : 1;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::CPU => $translator->trans('componentSlot.cpu.label', locale: $locale),
            self::COOLER => $translator->trans('componentSlot.cooler.label', locale: $locale),
            self::MOTHERBOARD => $translator->trans('componentSlot.motherboard.label', locale: $locale),
            self::RAM => $translator->trans('componentSlot.ram.label', locale: $locale),
            self::GPU => $translator->trans('componentSlot.gpu.label', locale: $locale),
            self::STORAGE => $translator->trans('componentSlot.storage.label', locale: $locale),
            self::CASE => $translator->trans('componentSlot.case.label', locale: $locale),
            self::PSU => $translator->trans('componentSlot.psu.label', locale: $locale),
        };
    }
}
