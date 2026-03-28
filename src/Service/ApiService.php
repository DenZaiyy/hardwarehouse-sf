<?php

namespace App\Service;

use App\DTO\Api\PaginationMeta;
use App\Exception\Api\ApiException;
use App\Exception\Api\ApiNotFoundException;
use App\Exception\Api\ApiServerException;
use App\Exception\Api\ApiUnavailableException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

readonly class ApiService
{
    public function __construct(
        private HttpClientInterface $apiClient,
        private SerializerInterface $serializer,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $dtoClass
     *
     * @return T
     *
     * @throws ApiNotFoundException
     * @throws ApiUnavailableException
     * @throws ApiServerException
     * @throws ApiException
     * @throws ExceptionInterface
     */
    public function fetchOne(string $endpoint, string $dtoClass): object
    {
        $response = $this->request('GET', $endpoint.'?active=true');

        /* @var T */
        return $this->serializer->deserialize($response->getContent(), $dtoClass, 'json');
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $dtoClass
     *
     * @return array<T>
     *
     * @throws ClientExceptionInterface
     * @throws ExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws \JsonException
     * @throws DecodingExceptionInterface
     */
    public function fetchAll(string $endpoint, string $dtoClass): array
    {
        $response = $this->request('GET', $endpoint.'?active=true');
        $data = $response->toArray();

        $payload = array_key_exists('data', $data) ? $data['data'] : $data;

        /** @var array<T> */
        return $this->serializer->deserialize(
            json_encode($payload, JSON_THROW_ON_ERROR),
            $dtoClass.'[]',
            'json'
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T>      $dtoClass
     * @param array<string, mixed> $params
     *
     * @return array{data: array<T>, total: int, meta: PaginationMeta}
     *
     * @throws ApiNotFoundException
     * @throws ApiUnavailableException
     * @throws ApiServerException
     * @throws ApiException
     * @throws ExceptionInterface
     * @throws \JsonException
     */
    public function fetchPaginated(string $endpoint, string $dtoClass, array $params = []): array
    {
        $newParams = array_merge(['active' => 'true'], $params);
        $query = http_build_query($newParams);
        $url = $endpoint.($query ? '?'.$query : '');

        /** @var array{data: array<T>, total: int, meta: array{page: int, limit: int, totalPages: int, hasNext: bool, hasPrev: bool}} $response */
        $response = $this->request('GET', $url)->toArray();

        $result = $this->serializer->deserialize(
            json_encode($response['data'], JSON_THROW_ON_ERROR),
            $dtoClass.'[]',
            'json'
        );

        /** @var array<T> $data */
        $data = $result;

        $meta = new PaginationMeta(
            total: $response['total'],
            page: $response['meta']['page'],
            limit: $response['meta']['limit'],
            totalPages: $response['meta']['totalPages'],
            hasNext: $response['meta']['hasNext'],
            hasPrev: $response['meta']['hasPrev'],
        );

        return [
            'data' => $data,
            'total' => $response['total'],
            'meta' => $meta,
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ApiNotFoundException
     * @throws ApiUnavailableException
     * @throws ApiServerException
     * @throws ApiException
     * @throws \JsonException
     */
    public function getData(string $endpoint): array
    {
        /** @var array<string, mixed> $result */
        $result = $this->request('GET', $endpoint)->toArray();

        return $result;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @throws ApiNotFoundException
     * @throws ApiUnavailableException
     * @throws ApiServerException
     * @throws ApiException
     * @throws \JsonException
     */
    public function search(string $searchTerm, array $params = []): array
    {
        $allParams = array_merge(['search' => $searchTerm], $params);
        $queryString = http_build_query($allParams);
        $endpoint = 'products?'.$queryString;

        /** @var array<string, mixed> $result */
        $result = $this->request('GET', $endpoint)->toArray();

        return $result;
    }

    /**
     * @throws ApiNotFoundException
     * @throws ApiUnavailableException
     * @throws ApiServerException
     * @throws ApiException
     */
    private function request(string $method, string $url): ResponseInterface
    {
        try {
            $response = $this->apiClient->request($method, $url);
            $response->getStatusCode();

            return $response;
        } catch (ClientExceptionInterface $e) {
            // 4xx
            $statusCode = $e->getResponse()->getStatusCode();
            if (404 === $statusCode) {
                throw new ApiNotFoundException($url);
            }
            throw new ApiException("Client error ($statusCode) at: $url", $statusCode, $e);
        } catch (ServerExceptionInterface $e) {
            // 5xx
            $this->logger->error('API server error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new ApiServerException($url);
        } catch (RedirectionExceptionInterface $e) {
            // 3xx non gérés
            throw new ApiException("Too many redirections at: $url", 0, $e);
        } catch (TransportExceptionInterface $e) {
            // Réseau down, timeout, DNS fail...
            $this->logger->critical('API unreachable', ['url' => $url, 'error' => $e->getMessage()]);
            throw new ApiUnavailableException($url);
        }
    }
}
