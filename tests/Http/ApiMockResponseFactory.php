<?php

declare(strict_types=1);

namespace App\Tests\Http;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Remplace l'API du catalogue pendant les tests (le client api.client est décoré dans config/services.yaml) :
 * aucune requête ne part vers l'API, les tests ne dépendent ni du réseau ni de ses données.
 *
 * Un test enregistre les réponses dont il a besoin avec respondWith(). À défaut, les listes
 * de catégories, de marques et de produits répondent vides, et toute autre ressource 404.
 */
final class ApiMockResponseFactory
{
    /** @var array<string, array{int, mixed}> */
    private static array $responses = [];

    public static function respondWith(string $path, mixed $body, int $status = 200): void
    {
        self::$responses[trim($path, '/')] = [$status, $body];
    }

    public static function reset(): void
    {
        self::$responses = [];
    }

    /** @param array<string, mixed> $options */
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        foreach (self::$responses as $registered => [$status, $body]) {
            if ($path === $registered || str_ends_with($path, '/'.$registered)) {
                return $this->json($body, $status);
            }
        }

        if (1 === preg_match('#(^|/)(categories|brands)$#', $path)) {
            return $this->json([]);
        }

        if (1 === preg_match('#(^|/)products$#', $path)) {
            return $this->json([
                'data' => [],
                'total' => 0,
                'meta' => ['page' => 1, 'limit' => 20, 'totalPages' => 0, 'hasNext' => false, 'hasPrev' => false],
            ]);
        }

        return $this->json(['error' => 'Not found'], 404);
    }

    private function json(mixed $body, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($body, JSON_THROW_ON_ERROR), [
            'http_code' => $status,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }
}
