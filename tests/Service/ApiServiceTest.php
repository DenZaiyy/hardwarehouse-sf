<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\Api\Categories\CategoryDto;
use App\DTO\Api\Products\ProductDto;
use App\Exception\Api\ApiNotFoundException;
use App\Exception\Api\ApiServerException;
use App\Service\ApiService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
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

    private function apiService(MockResponse $response): ApiService
    {
        return new ApiService(
            new MockHttpClient($response, 'https://api.example.test/api/v1/'),
            new Serializer(),
            new NullLogger(),
            new ArrayAdapter(),
        );
    }
}
