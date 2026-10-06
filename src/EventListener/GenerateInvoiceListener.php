<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Event\OrderConfirmedEvent;
use App\Service\Invoice\InvoiceGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Avant l'e-mail de confirmation, qui joint la facture. Un échec ne bloque pas l'e-mail : la facture
 * sera créée au premier téléchargement.
 */
#[AsEventListener(priority: 10)]
final readonly class GenerateInvoiceListener
{
    public function __construct(
        private InvoiceGenerator $invoiceGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(OrderConfirmedEvent $event): void
    {
        try {
            $this->invoiceGenerator->forOrder($event->order);
        } catch (\Throwable $e) {
            $this->logger->critical('Invoice generation failed', [
                'reference' => $event->order->getReference(),
                'exception' => $e,
            ]);
        }
    }
}
