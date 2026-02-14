<?php

namespace App\Dto\Api\Products;

use App\Dto\Api\Attributes\ProductAttributeValueDto;
use App\Dto\Api\Brands\BrandDto;
use App\Dto\Api\Categories\CategoryDto;

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
        public float $price,
        public bool $active,
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
