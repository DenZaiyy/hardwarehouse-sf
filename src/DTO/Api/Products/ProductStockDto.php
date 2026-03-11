<?php

namespace App\DTO\Api\Products;

readonly class ProductStockDto
{
    public function __construct(
        public int $quantity,
    ) {
    }
}
