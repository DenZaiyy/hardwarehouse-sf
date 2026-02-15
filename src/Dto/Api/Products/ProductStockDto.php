<?php

namespace App\Dto\Api\Products;

readonly class ProductStockDto
{
    public function __construct(
        public int $quantity,
    ) {
    }
}
