<?php

declare(strict_types=1);

namespace App\DTO\Catalog;

/**
 * Caractéristique d'un produit telle que la fiche l'affiche : libellé sans unité, valeur écrite pour le client.
 */
final readonly class ProductSpec
{
    public function __construct(
        public string $label,
        public string $value,
        /**
         * Valeur unique qui se lit seule en pastille : un Oui ou un Non, une liste (sockets, formats) restent dans le
         * tableau des caractéristiques
         */
        public bool $highlightable,
    ) {
    }
}
