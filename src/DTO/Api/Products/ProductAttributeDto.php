<?php

namespace App\DTO\Api\Products;

class ProductAttributeDto
{
    public function __construct(
        public string $id,
        public string $value,
    ) {
    }
}
