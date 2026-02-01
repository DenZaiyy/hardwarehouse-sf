<?php

namespace App\Dto\Api\Stocks;

readonly class StockDto
{
    public function __construct(
        public string $id,
        public int $minQuantity,
        public int $quantity,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $updatedAt = null,
        public ?StockProductDto $product = null,
    ) {
    }
}
