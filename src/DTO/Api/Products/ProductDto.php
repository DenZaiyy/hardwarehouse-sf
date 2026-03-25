<?php

namespace App\DTO\Api\Products;

use App\DTO\Api\Attributes\ProductAttributeValueDto;
use App\DTO\Api\Brands\BrandDto;
use App\DTO\Api\Categories\CategoryDto;

readonly class ProductDto
{
    /**
     * @param string[]                   $images
     * @param ProductAttributeValueDto[] $productAttributeValues
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $sku = null,
        public ?string $mpn = null,
        public ?string $ean13 = null,
        public float $price,
        public ?float $discountPrice = null,
        public ?float $discountAmount = null,
        public bool $promote = false,
        public bool $active = false,
        public ?string $thumbnail = null,
        public array $images = [],
        public ?string $shortDescription = null,
        public ?string $description = null,
        public ?CategoryDto $category = null,
        public ?BrandDto $brand = null,
        public ?ProductStockDto $stock = null,
        public array $productAttributeValues = [],
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $updatedAt = null,
    ) {
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
