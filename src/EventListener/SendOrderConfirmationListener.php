<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Event\OrderConfirmedEvent;
use App\Service\Invoice\InvoiceGenerator;
use App\Service\MailerService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class SendOrderConfirmationListener
{
    public function __construct(
        private MailerService $mailerService,
        private InvoiceGenerator $invoiceGenerator,
    ) {
    }

    public function __invoke(OrderConfirmedEvent $event): void
    {
        $invoice = $event->order->getInvoice();

        $this->mailerService->sendOrderConfirmation(
            $event->order,
            null === $invoice ? [] : [$this->invoiceGenerator->absolutePath($invoice) => 'facture-'.$invoice->getReference().'.pdf'],
        );
    }
}
