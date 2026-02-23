<?php

namespace App\Dto\Api\Attributes;

readonly class ProductAttributeValueDto
{
    public function __construct(
        public string $id,
        public string $value,
        public ?CategoryAttributeDto $categoryAttribute = null,
    ) {
    }
}
