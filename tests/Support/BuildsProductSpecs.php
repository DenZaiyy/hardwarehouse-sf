<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\DTO\Api\Products\ProductSpecDto;

/**
 * Construit un produit tel que le renvoie la lecture groupée de l'API (attributs indexés par code).
 */
trait BuildsProductSpecs
{
    /**
     * @param array<string, string|int|float|bool|list<string>> $attributes
     */
    private static function product(array $attributes = []): ProductSpecDto
    {
        $values = [];
        foreach ($attributes as $code => $value) {
            $values[$code] = match (true) {
                \is_array($value) => ['values' => $value],
                \is_bool($value) => ['value' => $value ? 'true' : 'false'],
                \is_int($value), \is_float($value) => ['value' => (string) $value, 'numericValue' => (float) $value],
                default => ['value' => $value],
            };
        }

        return new ProductSpecDto(id: bin2hex(random_bytes(12)), attributes: $values);
    }
}
