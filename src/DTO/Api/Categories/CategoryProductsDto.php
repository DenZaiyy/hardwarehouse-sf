<?php

namespace App\DTO\Api\Categories;

use App\DTO\Api\Brands\BrandDto;
use App\DTO\Api\Products\ProductStockDto;

readonly class CategoryProductsDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?bool $active,
        public float $price,
        public ?float $discountPrice = null,
        public ?float $discountAmount = null,
        public bool $promote = false,
        public ?string $shortDescription = null,
        public ?string $thumbnail = null,
        public ?BrandDto $brand = null,
        public ?ProductStockDto $stock = null,
    ) {
    }

    public function getBrand(): BrandDto
    {
        return $this->brand;
    }

    public function getStock(): ProductStockDto
    {
        return $this->stock;
    }
}
