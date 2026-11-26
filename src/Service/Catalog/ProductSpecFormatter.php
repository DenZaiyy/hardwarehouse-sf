<?php

declare(strict_types=1);

namespace App\Service\Catalog;

use App\DTO\Api\Attributes\ProductAttributeValueDto;
use App\DTO\Catalog\ProductSpec;
use App\Enum\AttributeType;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Met en forme les caractéristiques d'un produit pour sa fiche. L'API garde l'unité dans le libellé
 * (« Fréquence de base (GHz) ») : elle passe sur la valeur (« 3,5 GHz »), les nombres prennent l'écriture de la langue
 * de la page et les booléens se lisent Oui ou Non.
 */
final readonly class ProductSpecFormatter
{
    /** Pastilles de la fiche : un résumé à lire d'un coup d'œil, la liste complète reste dans l'onglet Caractéristiques */
    public const int HIGHLIGHTS = 4;

    /**
     * Unités reconnues entre parenthèses à la fin d'un libellé, avec leur écriture française. Une autre parenthèse
     * (« USB (Lightspeed) ») fait partie du libellé.
     */
    private const array UNITS = [
        'KB' => 'Ko', 'MB' => 'Mo', 'GB' => 'Go', 'TB' => 'To', 'MB/s' => 'Mo/s', 'GB/s' => 'Go/s',
        'Hz' => 'Hz', 'kHz' => 'kHz', 'MHz' => 'MHz', 'GHz' => 'GHz', 'W' => 'W', 'mm' => 'mm', 'cm' => 'cm',
        'ms' => 'ms', 'dB' => 'dB', 'dBA' => 'dBA', 'pouces' => 'pouces', 'RPM' => 'tr/min',
    ];

    public function __construct(private TranslatorInterface $translator)
    {
    }

    /**
     * @param array<ProductAttributeValueDto> $values valeurs d'attributs renvoyées par l'API
     *
     * @return list<ProductSpec> dans l'ordre d'affichage de la catégorie
     */
    public function specs(array $values): array
    {
        usort($values, static fn (ProductAttributeValueDto $a, ProductAttributeValueDto $b): int => ($a->categoryAttribute->displayOrder ?? 0) <=> ($b->categoryAttribute->displayOrder ?? 0));

        return array_values(array_filter(array_map($this->format(...), $values)));
    }

    /**
     * @param list<ProductSpec> $specs
     *
     * @return list<ProductSpec> les premières caractéristiques à valeur unique : un Oui ou un Non se lit mal seul, une
     *                           liste (sockets, formats de carte mère) ferait une pastille de plusieurs lignes
     */
    public function highlights(array $specs): array
    {
        return \array_slice(array_values(array_filter($specs, static fn (ProductSpec $spec): bool => $spec->highlightable)), 0, self::HIGHLIGHTS);
    }

    public function format(ProductAttributeValueDto $attributeValue): ?ProductSpec
    {
        $attribute = $attributeValue->categoryAttribute?->attribute;
        if (null === $attribute) {
            return null;
        }

        // L'API transmet les caractéristiques booléennes sous forme de texte, « true » ou « false »
        if (AttributeType::BOOLEAN === $attribute->type) {
            $key = 'true' === $attributeValue->value ? 'listing.product.specs.boolean_true' : 'listing.product.specs.boolean_false';

            return new ProductSpec($attribute->name, $this->translator->trans($key), false);
        }

        [$label, $unit] = $this->splitUnit($attribute->name);
        $value = AttributeType::NUMBER === $attribute->type && is_numeric($attributeValue->value)
            ? $this->number((float) $attributeValue->value)
            : $attributeValue->value;

        return new ProductSpec($label, null === $unit ? $value : $value."\u{a0}".$unit, AttributeType::MULTI_SELECT !== $attribute->type);
    }

    /**
     * @return array{string, string|null} libellé sans l'unité, unité écrite dans la langue de la page
     */
    private function splitUnit(string $name): array
    {
        if (1 !== preg_match('/^(.+?)\s*\(([^()]+)\)$/u', $name, $matches) || !isset(self::UNITS[$matches[2]])) {
            return [$name, null];
        }

        $french = str_starts_with($this->translator->getLocale(), 'fr');

        return [$matches[1], $french ? self::UNITS[$matches[2]] : $matches[2]];
    }

    private function number(float $number): string
    {
        $formatted = (new \NumberFormatter($this->translator->getLocale(), \NumberFormatter::DECIMAL))->format($number);

        return false === $formatted ? (string) $number : $formatted;
    }
}
