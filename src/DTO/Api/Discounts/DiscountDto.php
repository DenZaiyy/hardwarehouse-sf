<?php

declare(strict_types=1);

namespace App\DTO\Api\Discounts;

/**
 * Remise de l'API du catalogue, sur une catégorie ou sur un produit, en pourcentage ou en montant fixe. L'API calcule
 * elle-même le prix remisé des produits : la boutique ne s'en sert que pour annoncer la remise.
 */
readonly class DiscountDto
{
    public const string PERCENTAGE = 'PERCENTAGE';

    public function __construct(
        public string $id,
        public float $discountAmount,
        public string $discountType,
        public bool $active,
        public \DateTimeImmutable $startDate,
        public ?\DateTimeImmutable $endDate = null,
        public ?DiscountTargetDto $category = null,
        public ?DiscountTargetDto $product = null,
    ) {
    }
}
