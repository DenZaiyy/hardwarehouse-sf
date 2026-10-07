<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Event\OrderConfirmedEvent;
use App\Message\RecordOrderStockExit;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

/** Le stock sort de manière asynchrone : la réponse au webhook Stripe n'attend pas l'API du catalogue. */
#[AsEventListener]
final readonly class RecordStockExitListener
{
    public function __construct(
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(OrderConfirmedEvent $event): void
    {
        try {
            $this->bus->dispatch(new RecordOrderStockExit((string) $event->order->getReference()));
        } catch (\Throwable $e) {
            // Message perdu : la sortie de stock est à saisir à la main dans l'administration de l'API
            $this->logger->critical('Stock exit could not be queued', [
                'reference' => $event->order->getReference(),
                'exception' => $e,
            ]);
        }
    }
}
