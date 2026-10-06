<?php

declare(strict_types=1);

namespace App\Service\Stock;

use App\Entity\Order;
use App\Entity\OrderLine;
use App\Exception\Api\InsufficientStockException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Seule écriture de la boutique dans l'API du catalogue : la sortie de stock d'une commande payée,
 * authentifiée par le jeton partagé SHOP_API_TOKEN. L'API l'enregistre une seule fois par commande.
 */
final readonly class StockExitClient
{
    public function __construct(
        #[Autowire(service: 'api.client')]
        private HttpClientInterface $apiClient,
        #[Autowire('%env(SHOP_API_TOKEN)%')]
        private string $token,
    ) {
    }

    /**
     * @throws InsufficientStockException si le stock ne couvre plus la commande (inutile de réessayer)
     * @throws \LogicException           si le jeton manque ou si l'API rejette la requête (inutile de réessayer)
     * @throws \RuntimeException         si l'API ne répond pas ou est en erreur (à réessayer)
     */
    public function record(Order $order): void
    {
        if ('' === $this->token) {
            throw new \LogicException('SHOP_API_TOKEN is not configured: the catalogue API refuses stock exits without it.');
        }

        $lines = array_values(array_map(
            static fn (OrderLine $line): array => ['productId' => (string) $line->getProductId(), 'quantity' => (int) $line->getQuantity()],
            $order->getOrderLines()->toArray(),
        ));

        try {
            $status = $this->apiClient->request('POST', 'stock-exits', [
                'auth_bearer' => $this->token,
                'json' => ['orderReference' => (string) $order->getReference(), 'lines' => $lines],
            ])->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException(\sprintf('The catalogue API is unreachable for the stock exit of %s.', $order->getReference()), 0, $e);
        }

        if (409 === $status) {
            throw new InsufficientStockException(\sprintf('The catalogue API refused the stock exit of %s: insufficient stock.', $order->getReference()));
        }

        // Jeton refusé, produit sans stock, requête invalide : la même requête échouerait encore
        if ($status >= 400 && $status < 500 && 429 !== $status) {
            throw new \LogicException(\sprintf('The catalogue API rejected the stock exit of %s with %d.', $order->getReference(), $status));
        }

        if ($status >= 300) {
            throw new \RuntimeException(\sprintf('The catalogue API answered %d to the stock exit of %s.', $status, $order->getReference()));
        }
    }
}
