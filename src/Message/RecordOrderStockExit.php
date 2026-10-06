<?php

declare(strict_types=1);

namespace App\Message;

/** Sortie de stock d'une commande payée, enregistrée par l'API du catalogue et traitée par le worker. */
final readonly class RecordOrderStockExit
{
    public function __construct(
        public string $orderReference,
    ) {
    }
}
