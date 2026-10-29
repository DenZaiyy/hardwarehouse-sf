<?php

namespace App\Twig\Runtime;

use App\Service\Catalog\CatalogImageUrl;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * {{ product.thumbnail|catalog_image }} : adresse de l'image sur l'API. {{ product.thumbnail|catalog_image('@2x') }}
 * désigne une autre taille générée par l'API (voir CatalogImageUrl::resolve()).
 */
final readonly class CatalogImageExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(private CatalogImageUrl $catalogImageUrl)
    {
    }

    public function url(?string $path, string $variant = ''): string
    {
        return $this->catalogImageUrl->resolve($path, $variant) ?? '';
    }
}
