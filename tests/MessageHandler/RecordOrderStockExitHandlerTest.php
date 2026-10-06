<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Order;
use App\Entity\OrderLine;
use App\Enum\OrderStatus;
use App\Message\RecordOrderStockExit;
use App\MessageHandler\RecordOrderStockExitHandler;
use App\Tests\Http\ApiMockResponseFactory;
use App\Tests\Support\CreatesShopEntities;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Sortie de stock d'une commande payée, enregistrée par l'API : un stock insuffisant ne se règle pas en
 * réessayant (un administrateur est prévenu), une API indisponible si.
 */
final class RecordOrderStockExitHandlerTest extends KernelTestCase
{
    use CreatesShopEntities;

    public function testStockExitIsSentToTheApiWithTheOrderLines(): void
    {
        $order = $this->paidOrder();
        ApiMockResponseFactory::respondWith('stock-exits', ['orderReference' => $order->getReference(), 'movements' => 2], 201);

        $this->handle($order);

        $requests = array_values(array_filter(ApiMockResponseFactory::requests(), static fn (array $request): bool => str_ends_with($request['path'], 'stock-exits')));
        self::assertCount(1, $requests);
        $request = $requests[0];
        self::assertSame('POST', $request['method']);
        self::assertSame([
            'orderReference' => $order->getReference(),
            'lines' => [
                ['productId' => '65f0c0ffee0000000000aa01', 'quantity' => 2],
                ['productId' => '65f0c0ffee0000000000aa02', 'quantity' => 1],
            ],
        ], json_decode((string) $request['options']['body'], true));
        self::assertContains('Authorization: Bearer test-shop-token', $request['options']['normalized_headers']['authorization'] ?? []);
    }

    public function testInsufficientStockIsNotRetriedAndAlertsAnAdministrator(): void
    {
        $order = $this->paidOrder();
        ApiMockResponseFactory::respondWith('stock-exits', ['error' => 'Stock insuffisant', 'code' => 'CONFLICT'], 409);

        try {
            $this->handle($order);
            self::fail('Un stock insuffisant doit arrêter les reprises.');
        } catch (UnrecoverableMessageHandlingException) {
        }

        self::assertEmailCount(1);
    }

    public function testRejectedRequestIsNotRetried(): void
    {
        $order = $this->paidOrder();
        // Jeton refusé : réessayer ne changerait rien, le message part dans la file des échecs
        ApiMockResponseFactory::respondWith('stock-exits', ['error' => 'Unauthorized'], 401);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessageMatches('/401/');
        $this->handle($order);
    }

    public function testApiOutageIsRetried(): void
    {
        $order = $this->paidOrder();
        ApiMockResponseFactory::respondWith('stock-exits', ['error' => 'Erreur interne'], 503);

        try {
            $this->handle($order);
            self::fail('Une API indisponible doit lever une exception pour que Messenger réessaie.');
        } catch (\RuntimeException $e) {
            self::assertNotInstanceOf(UnrecoverableMessageHandlingException::class, $e);
            self::assertStringContainsString('503', $e->getMessage());
        }
    }

    #[Before]
    #[After]
    public function forgetApiRequests(): void
    {
        ApiMockResponseFactory::reset();
    }

    private function paidOrder(): Order
    {
        $order = $this->createOrder($this->createUser())->setStatus(OrderStatus::CONFIRMED);
        foreach (['65f0c0ffee0000000000aa01' => 2, '65f0c0ffee0000000000aa02' => 1] as $productId => $quantity) {
            $order->addOrderLine((new OrderLine())
                ->setProductId($productId)
                ->setProductName('Produit '.$productId)
                ->setProductSlug('produit-'.$productId)
                ->setQuantity($quantity)
                ->setUnitPrice('100.00')
                ->setTaxRate('0.2')
                ->setLineTotal((string) (120 * $quantity)));
        }
        $this->entityManager()->flush();

        return $order;
    }

    private function handle(Order $order): void
    {
        /** @var RecordOrderStockExitHandler $handler */
        $handler = static::getContainer()->get(RecordOrderStockExitHandler::class);
        $handler(new RecordOrderStockExit((string) $order->getReference()));
    }
}
