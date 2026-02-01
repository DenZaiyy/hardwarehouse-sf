<?php

namespace App\Dto\Api\Products;

readonly class ProductListItemDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public float $price,
        public ?string $shortDescription = null,
        public ?string $thumbnail = null,
    ) {
    }
}
