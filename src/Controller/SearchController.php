<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ApiService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class SearchController extends AbstractController
{
    public function __construct(
        private readonly ApiService $apiService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('search', '');

        if (strlen($query) < 2) {
            return new JsonResponse(['products' => []]);
        }

        try {
            // Utilisation du service API pour la recherche
            $data = $this->apiService->search($query, [
                'limit' => 5, // Limiter le nombre de résultats
            ]);

            // Formater les données pour le frontend
            $products = array_map(fn($product) => [
                'id' => $product['id'] ?? null,
                'name' => $product['name'] ?? 'Produit sans nom',
                'price' => $product['price'] ?? null,
                'thumbnail' => $product['thumbnail'] ?? null,
                'url' => $this->generateUrl('product.show', ['slug' => $product['slug'] ?? '']) ?? '#',
            ], $data['data'] ?? $data['products'] ?? []);

            return new JsonResponse([
                'products' => $products,
                'total' => count($products),
            ]);
        } catch (\Exception $e) {
            // Log l'erreur
            $this->logger->error('Search API error', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'products' => [],
                'error' => 'Erreur lors de la recherche',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
