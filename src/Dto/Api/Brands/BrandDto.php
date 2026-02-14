<?php

namespace App\Dto\Api\Brands;

readonly class BrandDto
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

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }
}
