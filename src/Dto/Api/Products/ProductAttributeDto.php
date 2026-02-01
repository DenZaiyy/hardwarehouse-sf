<?php

namespace App\Dto\Api\Products;

class ProductAttributeDto
{
    public function __construct(
        public string $id,
        public string $value,
    ) {
    }
}
