<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Event\OrderConfirmedEvent;
use App\Service\MailerService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class SendOrderConfirmationListener
{
    public function __construct(
        private MailerService $mailerService,
    ) {
    }

    public function __invoke(OrderConfirmedEvent $event): void
    {
        $this->mailerService->sendOrderConfirmation($event->order);
    }
}
