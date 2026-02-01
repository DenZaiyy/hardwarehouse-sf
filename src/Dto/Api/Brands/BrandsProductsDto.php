<?php

namespace App\Dto\Api\Brands;

use App\Dto\Api\Products\ProductStockDto;

readonly class BrandsProductsDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public float $price,
        public ?string $shortDescription = null,
        public ?string $thumbnail = null,
        public ?BrandDto $brand = null,
        public ?ProductStockDto $stock = null,
    ) {
    }
}
