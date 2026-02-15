<?php

namespace App\Dto\Api\Brands;

use App\Dto\Api\Products\ProductListItemDto;
use Symfony\Component\Serializer\Attribute\SerializedName;

readonly class BrandWithProductsDto
{
    /**
     * @param ProductListItemDto[] $products
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
