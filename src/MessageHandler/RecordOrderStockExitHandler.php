<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\Api\InsufficientStockException;
use App\Message\RecordOrderStockExit;
use App\Repository\OrderRepository;
use App\Service\MailerService;
use App\Service\Stock\StockExitClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Une API indisponible fait réessayer le message (3 fois, délai croissant, puis file « failed ») ;
 * un stock insuffisant ou une configuration absente ne se règle pas en réessayant.
 */
#[AsMessageHandler]
final readonly class RecordOrderStockExitHandler
{
    public function __construct(
        private OrderRepository $orderRepository,
        private StockExitClient $stockExitClient,
        private MailerService $mailerService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RecordOrderStockExit $message): void
    {
        $order = $this->orderRepository->findOneByReference($message->orderReference)
            ?? throw new UnrecoverableMessageHandlingException(\sprintf('Order %s not found.', $message->orderReference));

        try {
            $this->stockExitClient->record($order);
        } catch (InsufficientStockException $e) {
            // Commande payée sans stock (le dernier exemplaire vendu deux fois) : un administrateur choisit
            // entre réassort et remboursement
            $this->logger->critical('Stock exit refused for a paid order', ['reference' => $message->orderReference, 'exception' => $e]);
            $this->mailerService->sendAdminNotification(
                'Stock insuffisant pour une commande payée',
                \sprintf('La commande %s est payée, mais le stock ne la couvre plus : réassort ou remboursement à décider.', $message->orderReference),
                ['reference' => $message->orderReference],
            );

            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        } catch (\LogicException $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        }
    }
}
