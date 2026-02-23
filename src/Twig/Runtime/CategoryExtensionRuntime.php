<?php

namespace App\Twig\Runtime;

use App\Dto\Api\Categories\CategoryDto;
use App\Service\ApiService;
use Twig\Extension\RuntimeExtensionInterface;

class CategoryExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly ApiService $apiService,
    ) {
    }

    /**
     * @return CategoryDto[]
     */
    public function getCategories(): array
    {
        return $this->apiService->fetchAll('categories', CategoryDto::class);
    }
}
