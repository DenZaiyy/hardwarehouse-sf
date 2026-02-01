<?php

namespace App\Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;

class CurrencyExtensionRuntime implements RuntimeExtensionInterface
{
    public function getCurrency(int|float $value, string $currency = '€', string $separator = ','): string
    {
        $finalPrice = number_format($value, 2, $separator, ' ');
        return $finalPrice . ' ' . $currency;
    }
}
