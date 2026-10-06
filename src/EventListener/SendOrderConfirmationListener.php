<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Event\OrderConfirmedEvent;
use App\Service\Invoice\InvoiceGenerator;
use App\Service\MailerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class SendOrderConfirmationListener
{
    public function __construct(
        private MailerService $mailerService,
        private InvoiceGenerator $invoiceGenerator,
        private LoggerInterface $logger,
    ) {
    }

    /** Un échec reste ici : il n'empêche ni la facture ni la sortie de stock, écoutées à côté. */
    public function __invoke(OrderConfirmedEvent $event): void
    {
        $invoice = $event->order->getInvoice();

        try {
            $this->mailerService->sendOrderConfirmation(
                $event->order,
                null === $invoice ? [] : [$this->invoiceGenerator->absolutePath($invoice) => 'facture-'.$invoice->getReference().'.pdf'],
            );
        } catch (\Throwable $e) {
            $this->logger->critical('Order confirmation email failed', [
                'reference' => $event->order->getReference(),
                'exception' => $e,
            ]);
        }
    }
}
