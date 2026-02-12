<?php

namespace App\Service;

use App\Dto\Api\PaginationMeta;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class ApiService
{
    public function __construct(
        private HttpClientInterface $apiClient,
        private SerializerInterface $serializer,
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $dtoClass
     *
     * @return T
     *
     * @throws TransportExceptionInterface
     * @throws ExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function fetchOne(string $endpoint, string $dtoClass): object
    {
        $response = $this->apiClient->request('GET', $endpoint);

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
     * @throws TransportExceptionInterface
     * @throws ExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function fetchAll(string $endpoint, string $dtoClass): array
    {
        $response = $this->apiClient->request('GET', $endpoint);

        /** @var array<T> */
        return $this->serializer->deserialize($response->getContent(), $dtoClass.'[]', 'json');
    }

    /**
     * @template T of object
     *
     * @param class-string<T>      $dtoClass
     * @param array<string, mixed> $params
     *
     * @return array{data: array<T>, meta: PaginationMeta}
     *
     * @throws TransportExceptionInterface
     * @throws ExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws \JsonException
     */
    public function fetchPaginated(string $endpoint, string $dtoClass, array $params = []): array
    {
        $query = http_build_query($params);
        $url = $endpoint.($query ? '?'.$query : '');

        /** @var array{data: array, meta: array{total: int, page: int, limit: int, totalPages: int, hasNext: bool, hasPrev: bool}} $response */
        $response = $this->apiClient->request('GET', $url)->toArray();

        $result = $this->serializer->deserialize(
            json_encode($response['data'], JSON_THROW_ON_ERROR),
            $dtoClass.'[]',
            'json'
        );

        /** @var array<T> $data */
        $data = $result;

        $meta = new PaginationMeta(
            total: $response['meta']['total'],
            page: $response['meta']['page'],
            limit: $response['meta']['limit'],
            totalPages: $response['meta']['totalPages'],
            hasNext: $response['meta']['hasNext'],
            hasPrev: $response['meta']['hasPrev'],
        );

        return [
            'data' => $data,
            'meta' => $meta,
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getData(string $endpoint): array
    {
        /** @var array<string, mixed> $result */
        $result = $this->apiClient->request('GET', $endpoint)->toArray();

        return $result;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function search(string $searchTerm, array $params = []): array
    {
        $allParams = array_merge(['search' => $searchTerm], $params);
        $queryString = http_build_query($allParams);
        $endpoint = 'products?'.$queryString;

        /** @var array<string, mixed> $result */
        $result = $this->apiClient->request('GET', $endpoint)->toArray();

        return $result;
    }
}
