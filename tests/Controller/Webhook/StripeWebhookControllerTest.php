<?php

declare(strict_types=1);

namespace App\Tests\Controller\Webhook;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StripeWebhookControllerTest extends WebTestCase
{
    // Doit correspondre à STRIPE_WEBHOOK_SECRET dans phpunit.dist.xml
    private const string SECRET = 'whsec_test_hardwarehouse';

    public function testRejectsAnEventSignedWithAnotherSecret(): void
    {
        $client = static::createClient();
        $payload = $this->payload();

        $client->request('POST', '/webhook/stripe', server: [
            'HTTP_STRIPE_SIGNATURE' => $this->signature($payload, 'whsec_not_the_configured_secret'),
            'CONTENT_TYPE' => 'application/json',
        ], content: $payload);

        self::assertResponseStatusCodeSame(400);
    }

    public function testAcceptsAnEventSignedWithTheConfiguredSecret(): void
    {
        $client = static::createClient();
        $payload = $this->payload();

        $client->request('POST', '/webhook/stripe', server: [
            'HTTP_STRIPE_SIGNATURE' => $this->signature($payload, self::SECRET),
            'CONTENT_TYPE' => 'application/json',
        ], content: $payload);

        self::assertResponseIsSuccessful();
    }

    private function payload(): string
    {
        // Type non traité par la boutique : le contrôleur le journalise et répond 200.
        return json_encode([
            'id' => 'evt_test_webhook',
            'object' => 'event',
            'type' => 'customer.created',
            'data' => ['object' => ['id' => 'cus_test', 'object' => 'customer']],
        ], JSON_THROW_ON_ERROR);
    }

    private function signature(string $payload, string $secret): string
    {
        $timestamp = time();

        return sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', $timestamp.'.'.$payload, $secret));
    }
}
