<?php

namespace App\SEO\Schema;

use App\DTO\Api\Products\ProductDto;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class ProductSchemaBuilder
{
    private const string CURRENCY = 'EUR';
    private const string BASE_URL = 'https://hardwarehouse.fr';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(ProductDto $product): array
    {
        $productUrl = $this->urlGenerator->generate(
            'product.show',
            ['slug' => $product->slug],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $price = $product->discountPrice ?? $product->price;

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'url' => $productUrl,
            'image' => $this->buildImages($product),
            'description' => $product->shortDescription ?? $product->description,
            'category' => $product->category?->name,
            'brand' => $this->buildBrand($product),

            'sku' => $product->sku ?? null,
            'mpn' => $product->mpn ?? null,
            'gtin13' => $product->ean13 ?? null,

            'offers' => [
                '@type' => 'Offer',
                'url' => $productUrl,
                'priceCurrency' => self::CURRENCY,
                'price' => $this->formatPrice($price),
                'availability' => $this->resolveAvailability($product),
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => [
                    '@type' => 'Organization',
                    'name' => 'HardWareHouse',
                ],
            ],
        ];

        return $this->clean($schema);
    }

    /**
     * @return array<string, string>|null
     */
    private function buildBrand(ProductDto $product): ?array
    {
        $brandName = $product->brand?->name;

        if ($brandName === null || $brandName === '') {
            return null;
        }

        return [
            '@type' => 'Brand',
            'name' => $brandName,
        ];
    }

    /**
     * @return list<string>
     */
    private function buildImages(ProductDto $product): array
    {
        $images = [];

        $thumbnail = $this->absoluteUrl($product->thumbnail);
        if ($thumbnail !== null) {
            $images[] = $thumbnail;
        }

        foreach ($product->images as $image) {
            $absoluteImage = $this->absoluteUrl($image);

            if ($absoluteImage !== null && !in_array($absoluteImage, $images, true)) {
                $images[] = $absoluteImage;
            }
        }

        return array_values($images);
    }

    private function absoluteUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim(self::BASE_URL, '/') . '/' . ltrim($path, '/');
    }

    private function resolveAvailability(ProductDto $product): string
    {
        return ($product->stock?->quantity ?? 0) > 0
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';
    }

    private function formatPrice(float $price): string
    {
        return number_format($price, 2, '.', '');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function clean(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = $this->clean($value);
            }

            if ($value === null || $value === '' || $value === []) {
                unset($data[$key]);
                continue;
            }

            $data[$key] = $value;
        }

        return $data;
    }
}
