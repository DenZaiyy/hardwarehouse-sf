<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Order;

/**
 * Émis une seule fois par commande, quand le webhook Stripe la fait passer à « confirmée » : les suites
 * du paiement (e-mail, facture, stock) s'y abonnent sans alourdir le traitement du webhook.
 */
final readonly class OrderConfirmedEvent
{
    public function __construct(
        public Order $order,
    ) {
    }
}
