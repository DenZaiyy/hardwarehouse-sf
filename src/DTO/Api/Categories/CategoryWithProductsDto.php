<?php

namespace App\DTO\Api\Categories;

use App\DTO\Api\Products\ProductDto;
use Symfony\Component\Serializer\Attribute\SerializedName;

readonly class CategoryWithProductsDto
{
    /**
     * @param ProductDto[] $products
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?bool $active,
        public ?string $logo = null,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $updatedAt = null,
        #[SerializedName('Products')]
        public array $products = [],
    ) {
    }
}
