<?php

namespace App\Twig\Runtime;

use App\Service\Pricing\PriceCalculator;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Prix montrés au client : toujours TTC, calculés comme au panier (PriceCalculator), pour que la fiche et le
 * panier annoncent le même montant au centime près. L'API, le back-office et la facture restent hors taxe.
 */
final readonly class PriceExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(private PriceCalculator $priceCalculator)
    {
    }

    public function includingTax(float $priceExcludingTax): float
    {
        return $this->priceCalculator->unitPriceIncludingTax($priceExcludingTax) / 100;
    }

    /**
     * Taux de remise lu sur les deux prix : il vaut pour une remise en pourcentage comme pour un montant fixe,
     * que l'API transmet dans le même champ discountAmount.
     */
    public function discountRate(float $price, float $discountPrice): int
    {
        if ($price <= 0 || $discountPrice >= $price) {
            return 0;
        }

        return (int) round((1 - $discountPrice / $price) * 100);
    }
}
