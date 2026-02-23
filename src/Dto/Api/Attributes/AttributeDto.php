<?php

namespace App\Dto\Api\Attributes;

use App\Enum\AttributeType;

readonly class AttributeDto
{
    public function __construct(
        public string $id,
        public string $name,
        public AttributeType $type = AttributeType::TEXT,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $updatedAt = null,
    ) {
    }
}
