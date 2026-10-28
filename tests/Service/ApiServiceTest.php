<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\Api\Categories\CategoryDto;
use App\DTO\Api\Products\ProductDto;
use App\Exception\Api\ApiNotFoundException;
use App\Exception\Api\ApiServerException;
use App\Exception\Api\ApiUnavailableException;
use App\Service\ApiService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class ApiServiceTest extends TestCase
{
    public function testSearchOnlyAsksForActiveProducts(): void
    {
        $response = new MockResponse('{"data": [], "total": 0}');

        $this->apiService($response)->search('ryzen');

        parse_str((string) parse_url($response->getRequestUrl(), PHP_URL_QUERY), $query);
        self::assertSame('true', $query['active'] ?? null);
    }

    public function testMissingResourceRaisesApiNotFoundException(): void
    {
        $this->expectException(ApiNotFoundException::class);

        $this->apiService(new MockResponse('{"error": "Not found"}', ['http_code' => 404]))
            ->fetchOne('products/inconnu', ProductDto::class);
    }

    public function testServerErrorRaisesApiServerException(): void
    {
        $this->expectException(ApiServerException::class);

        $this->apiService(new MockResponse('', ['http_code' => 503]))
            ->fetchAll('categories', CategoryDto::class);
    }

    public function testApiThatStopsRespondingRaisesApiUnavailableException(): void
    {
        $this->expectException(ApiUnavailableException::class);

        // Les en-têtes arrivent, puis plus rien : une chaîne vide simule le délai d'inactivité dépassé
        $this->apiService(new MockResponse(['{"data": [', '']))
            ->fetchAll('categories', CategoryDto::class);
    }

    public function testApiDatesAreParsedWithoutTheDeprecatedLooseFallback(): void
    {
        // Date écrite par toISOString() côté API : millisecondes et « Z », hors du format RFC 3339 par défaut
        $response = new MockResponse('[{"id": "c1", "name": "Écrans", "slug": "ecrans", "active": true, "productsCount": 3, "createdAt": "2026-09-07T14:29:33.002Z"}]');
        $serializer = new Serializer(
            [new DateTimeNormalizer(), new ArrayDenormalizer(), new ObjectNormalizer(propertyTypeExtractor: new ReflectionExtractor())],
            [new JsonEncoder()],
        );

        // Le repli implicite sur le constructeur est déprécié en 8.1 et lèvera une exception en 9.0.
        // PHPUnit ignore cette dépréciation indirecte, d'où ce gestionnaire.
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            $categories = $this->apiService($response, $serializer)->fetchAll('categories', CategoryDto::class);
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations);
        self::assertEquals(new \DateTimeImmutable('2026-09-07T14:29:33.002Z'), $categories[0]->createdAt);
    }

    private function apiService(MockResponse $response, ?Serializer $serializer = null): ApiService
    {
        return new ApiService(
            new MockHttpClient($response, 'https://api.example.test/api/v1/'),
            $serializer ?? new Serializer(),
            new NullLogger(),
            new ArrayAdapter(),
        );
    }
}
