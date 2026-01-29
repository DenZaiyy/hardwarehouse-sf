<?php

namespace App\Service;

use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiService
{
    private HttpClientInterface $client;

    public function __construct(
        private readonly TagAwareCacheInterface $cache,
        private readonly string $baseUrl,
    ) {
        $this->client = HttpClient::create();
        $this->client = new CachingHttpClient($this->client, $this->cache);
    }

    /**
     * @param string $data
     * @param string|null $arg
     * @return array<string, mixed>
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getData(string $data, ?string $arg): array
    {
        try {
            $response = $this->client->request('GET', $this->baseUrl . $data . '/' . $arg);
            /** @var array<string, mixed> */
            return $response->toArray();
        } catch (TransportExceptionInterface $e) {
            echo $e->getMessage();
            /** @var array<string, mixed> */
            return [];
        }
    }
}
