<?php

declare(strict_types=1);

namespace App\Exception\Api;

/** L'API refuse une sortie de stock qui rendrait le stock négatif (409). */
final class InsufficientStockException extends \RuntimeException
{
}
