<?php

namespace App\Dto\Api\Categories;

use App\Dto\Api\Products\ProductDto;
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
        public ?string $logo = null,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $updatedAt = null,
        #[SerializedName('Products')]
        public array $products = [],
    ) {
    }
}
