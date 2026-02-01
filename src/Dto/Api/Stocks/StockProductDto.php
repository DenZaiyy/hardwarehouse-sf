<?php

namespace App\Dto\Api\Stocks;

readonly class StockProductDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public float $price,
        public ?string $thumbnail,
    ) {
    }
}
