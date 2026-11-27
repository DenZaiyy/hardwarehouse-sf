<?php

namespace App\Twig\Runtime;

use App\DTO\Api\Categories\CategoryDto;
use App\Exception\Api\ApiException;
use App\Service\Catalog\CategoryCatalog;
use Twig\Extension\RuntimeExtensionInterface;

class CategoryExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly CategoryCatalog $categoryCatalog,
    ) {
    }

    /**
     * @return CategoryDto[]
     */
    public function getCategories(): array
    {
        try {
            return $this->categoryCatalog->all();
        } catch (ApiException) {
            // Le menu se replie sur une liste vide : une panne de l'API du catalogue ne doit pas
            // rendre inaccessibles le panier, le compte ou le suivi des commandes.
            return [];
        }
    }
}
