<?php

declare(strict_types=1);

namespace App\Service\Catalog;

use App\DTO\Api\Discounts\DiscountDto;
use App\Exception\Api\ApiException;
use App\Service\ApiService;
use Psr\Clock\ClockInterface;

/**
 * Choisit la remise que l'accueil met en avant : une remise en pourcentage en cours (le filtre active=true de l'API ne
 * regarde pas les dates), dont la catégorie ou le produit est actif. Une remise sur toute une catégorie passe avant
 * celle d'un seul produit, puis la plus forte l'emporte. Sans remise en cours, l'accueil n'affiche pas de bandeau.
 */
final readonly class PromotionSelector
{
    public function __construct(
        private ApiService $apiService,
        private ClockInterface $clock,
    ) {
    }

    public function featured(): ?DiscountDto
    {
        try {
            $discounts = $this->apiService->fetchAllCached('discounts', DiscountDto::class);
        } catch (ApiException) {
            // Le bandeau est accessoire : une API des remises indisponible ne doit pas faire tomber l'accueil
            return null;
        }

        $now = $this->clock->now();
        $current = array_values(array_filter($discounts, static function (DiscountDto $discount) use ($now): bool {
            $target = $discount->category ?? $discount->product;

            return $discount->active
                && DiscountDto::PERCENTAGE === $discount->discountType
                && $discount->startDate <= $now
                && (null === $discount->endDate || $discount->endDate > $now)
                && null !== $target
                && false !== $target->active;
        }));

        usort($current, static fn (DiscountDto $a, DiscountDto $b): int => [null === $a->category, $b->discountAmount] <=> [null === $b->category, $a->discountAmount]);

        return $current[0] ?? null;
    }
}
