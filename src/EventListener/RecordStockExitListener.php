<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Event\OrderConfirmedEvent;
use App\Message\RecordOrderStockExit;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

/** Le stock sort de manière asynchrone : la réponse au webhook Stripe n'attend pas l'API du catalogue. */
#[AsEventListener]
final readonly class RecordStockExitListener
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(OrderConfirmedEvent $event): void
    {
        $this->bus->dispatch(new RecordOrderStockExit((string) $event->order->getReference()));
    }
}
