<?php

namespace App\Dto\Api\Attributes;

readonly class CategoryAttributeDto
{
    public function __construct(
        public string $id,
        public bool $required = false,
        public int $displayOrder = 0,
        public ?AttributeDto $attribute = null,
    ) {
    }
}
