<?php

namespace App\Service;

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
     * @param class-string<T> $dtoClass
     * @return T
     * @throws TransportExceptionInterface
     * @throws ExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function fetchOne(string $endpoint, string $dtoClass): object
    {
        $response = $this->apiClient->request('GET', $endpoint);

        /** @var T */
        return $this->serializer->deserialize($response->getContent(), $dtoClass, 'json');
    }

    /**
     * @template T of object
     * @param class-string<T> $dtoClass
     * @return T[]
     * @throws TransportExceptionInterface
     * @throws ExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function fetchAll(string $endpoint, string $dtoClass): array
    {
        $response = $this->apiClient->request('GET', $endpoint);

        /** @var T[] */
        return $this->serializer->deserialize($response->getContent(), $dtoClass . '[]', 'json');
    }

    /**
     * @return array<string, mixed>
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getData(string $endpoint): array
    {
        /** @var array<string, mixed> */
        return $this->apiClient->request('GET', $endpoint)->toArray();
    }
}
