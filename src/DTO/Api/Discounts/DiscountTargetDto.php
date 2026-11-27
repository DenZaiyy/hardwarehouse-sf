<?php

declare(strict_types=1);

namespace App\DTO\Api\Discounts;

/**
 * Catégorie ou produit visé par une remise, tel que GET /discounts le résume.
 */
readonly class DiscountTargetDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?bool $active = null,
    ) {
    }
}
