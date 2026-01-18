<?php

namespace App\Twig\Runtime;

use App\Service\ApiService;
use Twig\Extension\RuntimeExtensionInterface;

class CategoryExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly ApiService $apiService
    ) {
    }

    public function getCategories(): array
    {
        return $this->apiService->getData('categories', null);
    }
}
