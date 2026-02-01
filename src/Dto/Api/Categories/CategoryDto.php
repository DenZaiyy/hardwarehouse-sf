<?php

namespace App\Dto\Api\Categories;

readonly class CategoryDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $logo = null,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $updatedAt = null,
    ) {
    }
}
