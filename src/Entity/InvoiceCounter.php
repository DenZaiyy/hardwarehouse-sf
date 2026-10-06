<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Dernier numéro de facture attribué pour une année. Incrémenté dans la transaction qui crée la
 * facture : un échec annule aussi l'incrément, la numérotation reste sans rupture.
 */
#[ORM\Entity]
#[ORM\Table(name: 'invoice_counter')]
class InvoiceCounter
{
    #[ORM\Column]
    private int $lastNumber = 0;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        private int $year
    ) {
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getLastNumber(): int
    {
        return $this->lastNumber;
    }
}
