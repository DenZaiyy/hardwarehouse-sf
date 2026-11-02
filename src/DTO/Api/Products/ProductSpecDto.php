<?php

declare(strict_types=1);

namespace App\DTO\Api\Products;

/**
 * Produit renvoyé par la lecture groupée de l'API (GET /products?ids=…&include=attributes), ses attributs indexés
 * par code. Les accesseurs tolèrent un attribut dont seule la valeur texte est renseignée : un nombre ou une liste
 * d'une seule valeur s'en déduisent.
 */
readonly class ProductSpecDto
{
    /**
     * @param array<string, array{value?: string|null, values?: list<string>, numericValue?: float|int|null}> $attributes
     */
    public function __construct(
        public string $id,
        public string $name = '',
        public string $slug = '',
        public float $price = 0.0,
        public bool $active = false,
        public ?string $thumbnail = null,
        public ?ProductStockDto $stock = null,
        public array $attributes = [],
    ) {
    }

    public function value(string $code): ?string
    {
        $value = $this->attributes[$code]['value'] ?? null;

        return null === $value || '' === $value ? null : $value;
    }

    /**
     * Valeurs d'un attribut à choix multiples, une valeur simple comptant comme une liste d'un élément.
     *
     * @return list<string>
     */
    public function values(string $code): array
    {
        $values = $this->attributes[$code]['values'] ?? [];
        if ([] !== $values) {
            return $values;
        }

        $value = $this->value($code);

        return null === $value ? [] : [$value];
    }

    public function number(string $code): ?float
    {
        $number = $this->attributes[$code]['numericValue'] ?? null;
        if (null !== $number) {
            return (float) $number;
        }

        $value = $this->value($code);

        return null !== $value && is_numeric($value) ? (float) $value : null;
    }

    /** Attribut booléen, que l'API enregistre sous la forme « true » ou « false ». */
    public function flag(string $code): bool
    {
        return 'true' === $this->value($code);
    }
}
